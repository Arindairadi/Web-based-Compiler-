<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

if (!is_dir('tmp')) {
    mkdir('tmp', 0777, true);
}

$api_endpoint = $_GET['api'] ?? '';
if ($api_endpoint) {
    handleApiRequest($api_endpoint);
    exit;
}

function handleApiRequest($endpoint) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }
    ob_start();
    try {
        switch ($endpoint) {
            case 'compile': $result = handleCompile(); break;
            case 'visualize': $result = handleVisualize(); break;
            case 'step': $result = handleStep(); break;
            case 'download': handleDownload(); exit;
            case 'errors': $result = handleErrorReport(); break;
            default: $result = ['error' => 'Invalid API endpoint'];
        }
        ob_clean();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) { ob_clean(); echo json_encode(['error' => $e->getMessage()]); }
    catch (Throwable $t) { ob_clean(); echo json_encode(['error' => $t->getMessage()]); }
    ob_end_flush();
}

function handleCompile() {
    $input = file_get_contents('php://input');
    if (empty($input)) return ['error' => 'No input data received'];
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['error' => 'Invalid JSON input: ' . json_last_error_msg()];
    $source_code = $data['source_code'] ?? '';
    if (empty($source_code)) return ['error' => 'No source code provided'];
    $session_id = uniqid('compile_', true);
    $tmp_dir = "tmp/{$session_id}";
    if (!mkdir($tmp_dir, 0777, true) && !is_dir($tmp_dir)) return ['error' => 'Failed to create temporary directory'];
    if (file_put_contents("{$tmp_dir}/source.c", $source_code) === false) return ['error' => 'Failed to save source code'];
    $result = ['session_id' => $session_id, 'success' => true, 'stages' => [], 'outputs' => [], 'errors' => [], 'source_code' => $source_code];
    $lexicalResult = simulateTokens($source_code);
    $result['stages'][] = ['name' => 'Lexical Analysis', 'status' => $lexicalResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(50,200), 'tokens' => $lexicalResult['tokens'], 'errors' => $lexicalResult['errors']];
    $syntaxResult = simulateAST($source_code);
    $result['stages'][] = ['name' => 'Syntax Analysis', 'status' => $syntaxResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(100,300), 'ast' => $syntaxResult['ast'], 'errors' => $syntaxResult['errors']];
    $semanticResult = simulateSymbolTable($source_code);
    $result['stages'][] = ['name' => 'Semantic Analysis', 'status' => $semanticResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(80,250), 'symbol_table' => $semanticResult['symbol_table'], 'errors' => $semanticResult['errors']];
    $irResult = simulateIR($source_code);
    $result['stages'][] = ['name' => 'IR Generation', 'status' => $irResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(150,400), 'ir_code' => $irResult['ir'], 'errors' => $irResult['errors']];
    $optResult = simulateOptimization($source_code);
    $result['stages'][] = ['name' => 'Optimization', 'status' => $optResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(200,500), 'optimizations' => $optResult['optimizations'], 'errors' => $optResult['errors']];
    $codegenResult = simulateAssembly($source_code);
    $result['stages'][] = ['name' => 'Code Generation', 'status' => $codegenResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(250,600), 'assembly' => $codegenResult['assembly'], 'errors' => $codegenResult['errors']];
    $all_errors = [];
    foreach ($result['stages'] as $stage) if (!empty($stage['errors'])) { $all_errors = array_merge($all_errors, $stage['errors']); $result['success'] = false; }
    $result['errors'] = $all_errors;
    $result['outputs'] = ['tokens' => $result['stages'][0]['tokens'], 'ast' => $result['stages'][1]['ast'], 'ir' => $result['stages'][3]['ir_code'], 'asm' => $result['stages'][5]['assembly']];
    file_put_contents("{$tmp_dir}/result.json", json_encode($result, JSON_PRETTY_PRINT));
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $language = 'c';
    $success = $result['success'];
    $errorsCount = count($result['errors']);
    $compilationTimeMs = array_sum(array_column($result['stages'], 'duration'));
    logCompilation($userId, $language, $session_id, $source_code, $success, $errorsCount, $compilationTimeMs);
    if ($userId) logActivity($userId, 'compile', "Compiled C code, session: $session_id, errors: $errorsCount");
    return $result;
}

function handleVisualize() {
    $session_id = $_GET['session_id'] ?? '';
    $stage = $_GET['stage'] ?? 'all';
    $view = $_GET['view'] ?? 'pipeline';
    if (empty($session_id)) return ['error' => 'No session ID provided'];
    $result_file = "tmp/{$session_id}/result.json";
    if (!file_exists($result_file)) return ['error' => 'Session not found or compilation not completed'];
    $result = json_decode(file_get_contents($result_file), true);
    $viz_data = generateVisualizationData($result, $stage, $view);
    return $viz_data;
}

function handleStep() { return ['message' => 'Step execution not implemented in this demo', 'current_stage' => 'lexical', 'next_stage' => 'syntax']; }

function handleDownload() {
    $session_id = $_GET['session_id'] ?? '';
    $type = $_GET['type'] ?? 'source';
    if (empty($session_id)) { http_response_code(400); echo json_encode(['error' => 'No session ID provided']); exit; }
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    if ($userId) logActivity($userId, 'download', "Downloaded $type from session: $session_id");
    $tmp_dir = "tmp/{$session_id}";
    switch ($type) {
        case 'source':
            $file = "{$tmp_dir}/source.c";
            $filename = "source_{$session_id}.c";
            $content_type = 'text/plain';
            break;
        case 'ast':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
            $result = json_decode(file_get_contents($result_file), true);
            $content = json_encode($result['outputs']['ast'], JSON_PRETTY_PRINT);
            $filename = "ast_{$session_id}.json";
            $content_type = 'application/json';
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        case 'asm':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
            $result = json_decode(file_get_contents($result_file), true);
            $assembly = $result['outputs']['asm'] ?? [];
            $content = is_array($assembly) ? implode("\n", $assembly) : $assembly;
            $filename = "assembly_{$session_id}.asm";
            $content_type = 'text/plain';
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        default: http_response_code(400); echo json_encode(['error' => 'Invalid download type']); exit;
    }
    if (!file_exists($file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

function handleErrorReport() {
    $session_id = $_GET['session_id'] ?? '';
    if (empty($session_id)) return ['error' => 'No session ID provided'];
    $result_file = "tmp/{$session_id}/result.json";
    if (!file_exists($result_file)) return ['error' => 'Session not found'];
    $result = json_decode(file_get_contents($result_file), true);
    return ['session_id' => $session_id, 'errors' => $result['errors'] ?? [], 'warnings' => $result['warnings'] ?? [], 'total_errors' => count($result['errors'] ?? []), 'success' => $result['success'] ?? false];
}

function generateVisualizationData($result, $stage, $view) {
    $nodes = []; $edges = [];
    if ($view === 'pipeline') {
        $stages = [['id' => 'lexical', 'name' => 'Lexical Analysis', 'x' => -30, 'y' => 0, 'z' => 0], ['id' => 'syntax', 'name' => 'Syntax Analysis', 'x' => -18, 'y' => 0, 'z' => 0], ['id' => 'semantic', 'name' => 'Semantic Analysis', 'x' => -6, 'y' => 0, 'z' => 0], ['id' => 'ir', 'name' => 'IR Generation', 'x' => 6, 'y' => 0, 'z' => 0], ['id' => 'optimization', 'name' => 'Optimization', 'x' => 18, 'y' => 0, 'z' => 0], ['id' => 'codegen', 'name' => 'Code Generation', 'x' => 30, 'y' => 0, 'z' => 0]];
        foreach ($stages as $stage_info) {
            $stage_data = null;
            foreach ($result['stages'] as $s) if (strpos(strtolower($s['name']), strtolower($stage_info['id'])) !== false) { $stage_data = $s; break; }
            $has_errors = $stage_data && !empty($stage_data['errors']);
            $nodes[] = ['id' => $stage_info['id'], 'name' => $stage_info['name'], 'type' => 'stage', 'color' => $has_errors ? '#e74c3c' : '#2ecc71', 'position' => ['x' => $stage_info['x'], 'y' => $stage_info['y'], 'z' => $stage_info['z']], 'size' => 3, 'status' => $stage_data['status'] ?? 'pending', 'duration' => $stage_data['duration'] ?? 0, 'has_errors' => $has_errors, 'error_count' => $has_errors ? count($stage_data['errors']) : 0];
        }
        for ($i = 0; $i < count($stages) - 1; $i++) $edges[] = ['from' => $stages[$i]['id'], 'to' => $stages[$i+1]['id'], 'type' => 'pipeline', 'color' => '#3498db'];
    } elseif ($view === 'ast') {
        if (isset($result['outputs']['ast'])) { $ast = $result['outputs']['ast']; $node_id = 0; createASTNodes($ast, $nodes, $edges, $node_id, 0, 10, 0, -1); }
    } elseif ($view === 'cfg') {
        $cfg_nodes = [['id' => 'entry', 'name' => 'Entry', 'x' => 0, 'y' => 20, 'z' => 0, 'type' => 'entry'], ['id' => 'decl', 'name' => 'Declarations', 'x' => -10, 'y' => 10, 'z' => 0, 'type' => 'declaration'], ['id' => 'init', 'name' => 'Initialization', 'x' => -10, 'y' => 0, 'z' => 0, 'type' => 'statement'], ['id' => 'cond', 'name' => 'Condition Check', 'x' => 0, 'y' => -10, 'z' => 0, 'type' => 'condition'], ['id' => 'body', 'name' => 'Loop Body', 'x' => 10, 'y' => 0, 'z' => 0, 'type' => 'statement'], ['id' => 'inc', 'name' => 'Increment', 'x' => 10, 'y' => 10, 'z' => 0, 'type' => 'statement'], ['id' => 'exit', 'name' => 'Exit', 'x' => 0, 'y' => -20, 'z' => 0, 'type' => 'exit']];
        foreach ($cfg_nodes as $node) $nodes[] = ['id' => $node['id'], 'name' => $node['name'], 'type' => $node['type'], 'color' => getCFGNodeColor($node['type']), 'position' => ['x' => $node['x'], 'y' => $node['y'], 'z' => $node['z']], 'size' => 2.5, 'has_errors' => false, 'error_count' => 0];
        $cfg_edges = [['from' => 'entry', 'to' => 'decl', 'type' => 'control_flow'], ['from' => 'decl', 'to' => 'init', 'type' => 'control_flow'], ['from' => 'init', 'to' => 'cond', 'type' => 'control_flow'], ['from' => 'cond', 'to' => 'body', 'type' => 'conditional', 'label' => 'true'], ['from' => 'cond', 'to' => 'exit', 'type' => 'conditional', 'label' => 'false'], ['from' => 'body', 'to' => 'inc', 'type' => 'control_flow'], ['from' => 'inc', 'to' => 'cond', 'type' => 'control_flow']];
        foreach ($cfg_edges as $edge) $edges[] = $edge;
    } elseif ($view === 'memory') {
        $memory_sections = [['id' => 'text', 'name' => 'Text (Code)', 'x' => -20, 'y' => 15, 'z' => 0, 'size' => 4, 'height' => 3], ['id' => 'data', 'name' => 'Data', 'x' => -10, 'y' => 15, 'z' => 0, 'size' => 3, 'height' => 4], ['id' => 'bss', 'name' => 'BSS', 'x' => 0, 'y' => 15, 'z' => 0, 'size' => 3, 'height' => 5], ['id' => 'heap', 'name' => 'Heap', 'x' => 10, 'y' => 5, 'z' => 0, 'size' => 4, 'height' => 6], ['id' => 'stack', 'name' => 'Stack', 'x' => 20, 'y' => -5, 'z' => 0, 'size' => 5, 'height' => 8]];
        foreach ($memory_sections as $section) $nodes[] = ['id' => $section['id'], 'name' => $section['name'], 'type' => 'memory_section', 'color' => getMemorySectionColor($section['id']), 'position' => ['x' => $section['x'], 'y' => $section['y'], 'z' => $section['z']], 'size' => $section['size'], 'height' => $section['height'], 'has_errors' => false, 'error_count' => 0];
        $allocations = [['id' => 'var_x', 'name' => 'int x = 10', 'x' => -20, 'y' => 5, 'z' => 5, 'size' => 1.5], ['id' => 'var_y', 'name' => 'int y = 20', 'x' => -20, 'y' => 2, 'z' => 5, 'size' => 1.5], ['id' => 'var_result', 'name' => 'int result', 'x' => -20, 'y' => -1, 'z' => 5, 'size' => 1.5], ['id' => 'func_main', 'name' => 'main()', 'x' => -20, 'y' => 8, 'z' => 5, 'size' => 1.5]];
        foreach ($allocations as $alloc) $nodes[] = ['id' => $alloc['id'], 'name' => $alloc['name'], 'type' => 'allocation', 'color' => '#9b59b6', 'position' => ['x' => $alloc['x'], 'y' => $alloc['y'], 'z' => $alloc['z']], 'size' => $alloc['size'], 'has_errors' => false, 'error_count' => 0];
    }
    return ['nodes' => $nodes, 'edges' => $edges, 'view' => $view, 'stage' => $stage, 'session_id' => $result['session_id'] ?? ''];
}

function getCFGNodeColor($type) { $colors = ['entry' => '#2ecc71', 'exit' => '#e74c3c', 'condition' => '#f39c12', 'statement' => '#3498db', 'declaration' => '#9b59b6']; return $colors[$type] ?? '#95a5a6'; }
function getMemorySectionColor($section) { $colors = ['text' => '#3498db', 'data' => '#2ecc71', 'bss' => '#f39c12', 'heap' => '#9b59b6', 'stack' => '#e74c3c']; return $colors[$section] ?? '#95a5a6'; }

function createASTNodes($node, &$nodes, &$edges, &$node_id, $x, $y, $z, $parent_id) {
    $current_id = $node_id++;
    $node_type = $node['type'] ?? 'Unknown';
    $node_name = $node['name'] ?? $node_type;
    $node_value = $node['value'] ?? '';
    $nodes[] = ['id' => 'ast_node_' . $current_id, 'name' => $node_name . ($node_value ? ': ' . $node_value : ''), 'type' => $node_type, 'color' => getASTNodeColor($node_type), 'position' => ['x' => $x, 'y' => $y, 'z' => $z], 'size' => 2, 'has_errors' => false, 'error_count' => 0];
    if ($parent_id >= 0) $edges[] = ['from' => 'ast_node_' . $parent_id, 'to' => 'ast_node_' . $current_id, 'type' => 'parent_child', 'color' => '#2ecc71'];
    $child_index = 0;
    foreach ($node as $key => $value) {
        if ($key === 'type' || $key === 'name' || $key === 'value') continue;
        if (is_array($value) && isset($value['type'])) {
            $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5);
            createASTNodes($value, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++;
        } elseif (is_array($value) && is_array(reset($value)) && isset(reset($value)['type'])) {
            foreach ($value as $child) if (is_array($child) && isset($child['type'])) { $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5); createASTNodes($child, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++; }
        }
    }
    return $current_id;
}
function getASTNodeColor($type) { $colors = ['Program' => '#3498db', 'FunctionDeclaration' => '#2ecc71', 'ReturnStatement' => '#e74c3c', 'PrintStatement' => '#f39c12', 'Literal' => '#9b59b6', 'Expression' => '#1abc9c']; return $colors[$type] ?? '#95a5a6'; }

function simulateTokens($code) {
    $tokens = []; $errors = [];
    $code = preg_replace('/\/\/.*$/m', '', $code);
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    $lines = explode("\n", $code);
    foreach ($lines as $lineNum => $line) {
        $line = trim($line); if (empty($line)) continue;
        if (strpos($line, '#') === 0) { $tokens[] = ['type' => 'PREPROCESSOR', 'value' => $line, 'line' => $lineNum + 1]; continue; }
        $quoteCount = substr_count($line, '"'); if ($quoteCount % 2 != 0) $errors[] = ['type' => 'lexical', 'message' => 'Unterminated string literal', 'line' => $lineNum + 1, 'severity' => 'error'];
        $words = preg_split('/(\s+|(?<=[(){};=+\-\/*<>!,])|(?=[(){};=+\-\/*<>!,]))/', $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($words as $word) {
            $word = trim($word); if (empty($word)) continue;
            $token = ['type' => 'IDENTIFIER', 'value' => $word, 'line' => $lineNum + 1];
            $keywords = ['int', 'return', 'if', 'else', 'for', 'while', 'printf', 'main', 'char', 'float', 'double', 'void', 'include'];
            if (in_array($word, $keywords)) $token['type'] = 'KEYWORD';
            elseif (is_numeric($word)) $token['type'] = 'LITERAL';
            elseif (preg_match('/^".*"$/', $word) || preg_match("/^'.*'$/", $word)) $token['type'] = 'STRING_LITERAL';
            elseif (preg_match('/^[+\-*/=<>!&|]+$/', $word)) $token['type'] = 'OPERATOR';
            elseif (preg_match('/^[(){}\[\];,]$/', $word)) $token['type'] = 'PUNCTUATOR';
            $tokens[] = $token;
        }
    }
    return ['tokens' => $tokens, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateAST($code) {
    $errors = [];
    $lines = explode("\n", $code);
    $brace_count = 0;
    foreach ($lines as $lineNum => $line) {
        $line = trim($line); if (empty($line)) continue;
        if (strpos($line, '#') === 0 || strpos($line, '//') === 0) continue;
        $brace_count += substr_count($line, '{'); $brace_count -= substr_count($line, '}');
        if (!empty($line) && substr($line, -1) !== ';' && substr($line, -1) !== '{' && substr($line, -1) !== '}' && substr($line, -1) !== ':' && !preg_match('/^(if|for|while|else|do)\b/', $line) && !preg_match('/^#/', $line) && !preg_match('/^\/\//', $line) && !preg_match('/^\/\*/', $line) && !preg_match('/^\*/', $line)) {
            if (preg_match('/(=\s*[^;]+|printf\s*\(|return\s+[^;])$/', $line)) $errors[] = ['type' => 'syntax', 'message' => 'Missing semicolon', 'line' => $lineNum + 1, 'severity' => 'error'];
        }
    }
    if ($brace_count != 0) $errors[] = ['type' => 'syntax', 'message' => 'Unbalanced braces', 'severity' => 'error'];
    $ast = ['type' => 'Program', 'body' => [['type' => 'FunctionDeclaration', 'name' => 'main', 'params' => [], 'body' => [['type' => 'ReturnStatement', 'value' => ['type' => 'Literal', 'value' => 0]]]]]];
    if (strpos($code, 'printf') !== false) $ast['body'][0]['body'] = array_merge([['type' => 'PrintStatement', 'format' => '"Hello, World!"', 'arguments' => []]], $ast['body'][0]['body']);
    return ['ast' => $ast, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateSymbolTable($code) {
    $symbol_table = ['global' => [], 'functions' => []];
    $errors = []; $lines = explode("\n", $code); $currentFunction = null; $declared_vars = [];
    $library_functions = ['printf', 'scanf', 'malloc', 'free', 'strlen', 'strcpy']; $valid_identifiers = ['stdio', 'h', 'd', 'n', 's', 'c', 'f'];
    foreach ($lines as $lineNum => $line) {
        $line = trim($line); $line_without_strings = preg_replace('/"[^"]*"/', 'STRING', $line);
        if (strpos($line, '#') === 0 || strpos($line, '//') === 0) continue;
        if (preg_match('/^\s*(int|void|float|double|char)\s+(\w+)\s*\(([^)]*)\)/', $line, $matches)) {
            $currentFunction = $matches[2]; $symbol_table['functions'][$currentFunction] = ['name' => $currentFunction, 'return_type' => $matches[1], 'params' => [], 'variables' => [], 'line' => $lineNum + 1]; $declared_vars[] = $currentFunction;
        } elseif (preg_match('/^\s*(int|float|double|char)\s+(\w+)\s*(=\s*[^;]+)?\s*;/', $line_without_strings, $matches)) {
            $var_name = $matches[2]; $declared_vars[] = $var_name;
            if ($currentFunction) $symbol_table['functions'][$currentFunction]['variables'][] = ['name' => $var_name, 'type' => $matches[1], 'initialized' => isset($matches[3]), 'line' => $lineNum + 1];
            else $symbol_table['global'][] = ['name' => $var_name, 'type' => $matches[1], 'line' => $lineNum + 1];
        }
        if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $line_without_strings, $matches)) {
            foreach ($matches[1] as $identifier) {
                if (in_array($identifier, ['int','return','if','else','for','while','printf','main','char','float','double','void','include']) || in_array($identifier, $library_functions) || in_array($identifier, $valid_identifiers) || $identifier === 'STRING' || strpos($identifier, 'stdio') === 0) continue;
                if (strpos($line, $identifier . '(') !== false) continue;
                if (!in_array($identifier, $declared_vars) && !in_array($identifier, array_keys($symbol_table['functions']))) {
                    if (strpos($line, '"' . $identifier . '"') === false && strpos($line, "'" . $identifier . "'") === false) $errors[] = ['type' => 'semantic', 'message' => "Undeclared identifier: $identifier", 'line' => $lineNum + 1, 'severity' => 'warning'];
                }
            }
        }
    }
    return ['symbol_table' => $symbol_table, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateIR($code) {
    $ir = []; $errors = [];
    $lines = explode("\n", $code);
    foreach ($lines as $line) {
        $line = trim($line); if (empty($line) || strpos($line, '#') === 0 || strpos($line, '//') === 0) continue;
        if (preg_match('/int\s+(\w+)\s*=\s*(\d+)\s*;/', $line, $matches)) $ir[] = ['op' => 'store', 'dest' => $matches[1], 'value' => $matches[2]];
        elseif (preg_match('/int\s+(\w+)\s*=\s*(\w+)\s*\+\s*(\w+)\s*;/', $line, $matches)) $ir[] = ['op' => 'add', 'dest' => $matches[1], 'src1' => $matches[2], 'src2' => $matches[3]];
        elseif (preg_match('/printf\(".*%d.*",\s*(\w+)\)/', $line, $matches)) $ir[] = ['op' => 'print', 'value' => $matches[1]];
        elseif (preg_match('/return\s+(\d+)\s*;/', $line, $matches)) $ir[] = ['op' => 'ret', 'value' => $matches[1]];
    }
    return ['ir' => $ir, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateOptimization($code) { return ['optimizations' => ['Constant folding', 'Dead code elimination', 'Loop invariant code motion', 'Register allocation'], 'errors' => [], 'has_errors' => false]; }

function simulateAssembly($code) {
    $assembly = []; $errors = [];
    $assembly[] = '; Generated Assembly Code'; $assembly[] = '; From: ' . substr($code, 0, 50) . '...'; $assembly[] = '';
    $assembly[] = '.section .text'; $assembly[] = '.global main'; $assembly[] = ''; $assembly[] = 'main:'; $assembly[] = '    push   %rbp'; $assembly[] = '    mov    %rsp, %rbp'; $assembly[] = '';
    if (strpos($code, 'printf') !== false) { $assembly[] = '    ; Printf implementation'; $assembly[] = '    lea    .LC0(%rip), %rdi'; $assembly[] = '    mov    $0, %eax'; $assembly[] = '    call   printf@PLT'; $assembly[] = ''; }
    if (strpos($code, 'int ') !== false && preg_match_all('/int\s+(\w+)\s*=\s*(\d+)/', $code, $matches)) {
        $assembly[] = '    ; Variable declarations';
        for ($i = 0; $i < count($matches[1]); $i++) { $var = $matches[1][$i]; $val = $matches[2][$i]; $offset = ($i + 1) * 4; $assembly[] = "    movl   \$$val, -{$offset}(%rbp)   ; $var = $val"; }
        $assembly[] = '';
    }
    if (strpos($code, '+') !== false && preg_match('/(\w+)\s*=\s*(\w+)\s*\+\s*(\w+)/', $code)) { $assembly[] = '    ; Addition operation'; $assembly[] = '    mov    -4(%rbp), %eax'; $assembly[] = '    add    -8(%rbp), %eax'; $assembly[] = '    mov    %eax, -12(%rbp)'; $assembly[] = ''; }
    $assembly[] = '    mov    $0, %eax        ; return 0'; $assembly[] = '    pop    %rbp'; $assembly[] = '    ret'; $assembly[] = '';
    if (strpos($code, 'printf') !== false) { $assembly[] = '.section .rodata'; $assembly[] = '.LC0:'; $assembly[] = '    .string "Result: %d\\n"'; }
    return ['assembly' => $assembly, 'errors' => $errors, 'has_errors' => false];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>3D C Compiler Visualizer - Final Project</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
            color: #ccd6f6;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: rgba(17, 34, 64, 0.95);
            padding: 12px 15px;
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .header h1 {
            color: #64ffda;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 250px;
        }
        
        .subtitle {
            color: #8892b0;
            font-size: 0.85rem;
            font-weight: 400;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(10, 25, 47, 0.6);
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid rgba(100, 255, 218, 0.2);
        }
        
        .user-info a {
            color: #64ffda;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }
        
        .user-info a:hover {
            color: #ffffff;
        }
        
        .user-info span {
            color: #ccd6f6;
            font-size: 0.9rem;
        }
        
        .container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 140px);
            padding: 10px;
            flex: 1;
            overflow: hidden;
        }
        
        .main-content {
            display: flex;
            flex: 1;
            gap: 10px;
            overflow: hidden;
            flex-direction: column;
        }
        
        @media (min-width: 992px) {
            .main-content {
                flex-direction: row;
            }
        }
        
        .panel {
            background: rgba(17, 34, 64, 0.7);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(100, 255, 218, 0.1);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            min-height: 300px;
        }
        
        .controls-panel {
            width: 100%;
            max-width: 100%;
            order: 1;
        }
        
        @media (min-width: 992px) {
            .controls-panel {
                width: 400px;
                min-width: 400px;
                order: 1;
            }
        }
        
        .visualization-panel {
            flex: 1;
            order: 2;
            min-height: 400px;
        }
        
        .output-panel {
            width: 100%;
            max-width: 100%;
            order: 3;
        }
        
        @media (min-width: 992px) {
            .output-panel {
                width: 450px;
                min-width: 450px;
                order: 3;
            }
        }
        
        .panel-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(100, 255, 218, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .panel-header h2 {
            color: #64ffda;
            font-size: 1.2rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .panel-header i {
            color: #64ffda;
            font-size: 1.1rem;
        }
        
        /* Left Panel - Controls */
        .controls-panel {
            overflow-y: auto;
        }
        
        .control-group {
            margin-bottom: 15px;
        }
        
        .control-group label {
            display: block;
            margin-bottom: 6px;
            color: #64ffda;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        select, input[type="range"] {
            width: 100%;
            padding: 8px 10px;
            background: rgba(10, 25, 47, 0.8);
            border: 1px solid rgba(100, 255, 218, 0.2);
            border-radius: 6px;
            color: #e6f1ff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        select:focus, input[type="range"]:focus {
            outline: none;
            border-color: #64ffda;
            box-shadow: 0 0 0 2px rgba(100, 255, 218, 0.1);
        }
        
        select option {
            background: #112240;
            color: #e6f1ff;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #8892b0;
            font-weight: 400;
            transition: color 0.3s;
        }
        
        .checkbox-group label:hover {
            color: #e6f1ff;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            background: linear-gradient(135deg, #64ffda, #00d9a6);
            color: #0a192f;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 120px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(100, 255, 218, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn.secondary {
            background: linear-gradient(135deg, #8892b0, #a8b2d1);
            color: #0a192f;
        }
        
        .btn.danger {
            background: linear-gradient(135deg, #ff6b6b, #ff4757);
            color: white;
        }
        
        /* Center Panel - Visualization */
        .visualization-panel {
            position: relative;
        }
        
        #visualization-canvas {
            width: 100%;
            height: 100%;
            border-radius: 6px;
        }
        
        .visualization-controls {
            position: absolute;
            bottom: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(10, 25, 47, 0.8);
            border: 1px solid rgba(100, 255, 218, 0.3);
            color: #64ffda;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .icon-btn:hover {
            background: rgba(100, 255, 218, 0.1);
            transform: scale(1.1);
        }
        
        /* Right Panel - Output */
        .output-panel {
            overflow-y: auto;
        }
        
        .status-bar {
            margin-top: auto;
            padding: 12px;
            background: rgba(10, 25, 47, 0.8);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        #status-message {
            margin-bottom: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .progress-bar {
            height: 5px;
            background: rgba(136, 146, 176, 0.2);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #64ffda, #00d9a6);
            width: 0%;
            transition: width 0.5s;
        }
        
        .error-indicator {
            background: linear-gradient(90deg, #ff6b6b, #ff4757);
        }
        
        .code-editor {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-top: 15px;
        }
        
        .code-editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .code-editor-header h3 {
            color: #64ffda;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        textarea {
            flex: 1;
            background: #0a192f;
            color: #e6f1ff;
            border: 1px solid rgba(100, 255, 218, 0.2);
            border-radius: 6px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: none;
            white-space: pre;
            overflow: auto;
            tab-size: 4;
            transition: border 0.3s;
            min-height: 200px;
        }
        
        textarea:focus {
            outline: none;
            border-color: #64ffda;
        }
        
        /* Output Tabs */
        .output-tabs {
            display: flex;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .output-tab {
            padding: 8px 12px;
            background: none;
            border: none;
            color: #8892b0;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .output-tab:hover {
            color: #e6f1ff;
        }
        
        .output-tab.active {
            color: #64ffda;
            border-bottom-color: #64ffda;
        }
        
        .output-tab.error {
            color: #ff6b6b;
        }
        
        .output-content {
            flex: 1;
            background: #0a192f;
            border-radius: 6px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-y: auto;
            display: none;
            max-height: 300px;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .output-content.active {
            display: block;
        }
        
        .stage-info {
            margin-top: 15px;
            padding: 15px;
            background: rgba(10, 25, 47, 0.8);
            border-radius: 6px;
            font-size: 0.85rem;
            line-height: 1.5;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .error-section {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 6px;
            border: 1px solid rgba(255, 107, 107, 0.3);
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }
        
        .error-item {
            padding: 10px;
            margin-bottom: 8px;
            background: rgba(255, 107, 107, 0.2);
            border-radius: 5px;
            border-left: 4px solid #ff6b6b;
            font-size: 0.85rem;
        }
        
        .error-warning {
            border-left-color: #ffa502;
        }
        
        .stage-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .status-completed {
            background: rgba(100, 255, 218, 0.2);
            color: #64ffda;
        }
        
        .status-failed {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }
        
        .status-pending {
            background: rgba(136, 146, 176, 0.2);
            color: #8892b0;
        }
        
        .tooltip {
            position: absolute;
            background: rgba(10, 25, 47, 0.95);
            color: #e6f1ff;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #64ffda;
            pointer-events: none;
            z-index: 1000;
            max-width: 250px;
            font-size: 0.8rem;
            display: none;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
        }
        
        .footer {
            background: rgba(17, 34, 64, 0.95);
            padding: 12px 15px;
            border-top: 1px solid rgba(100, 255, 218, 0.1);
            backdrop-filter: blur(10px);
            flex-shrink: 0;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .authors {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
            justify-content: center;
        }
        
        .author {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64ffda;
            font-size: 0.9rem;
        }
        
        .course-info {
            color: #8892b0;
            font-size: 0.85rem;
            text-align: center;
            flex: 1;
        }
        
        .university {
            color: #64ffda;
            font-weight: 600;
        }
        
        .github-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64ffda;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
            white-space: nowrap;
        }
        
        .github-link:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(10, 25, 47, 0.5);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.3);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 255, 218, 0.5);
        }
        
        /* Mobile-specific styles */
        @media (max-width: 991px) {
            .container {
                height: auto;
                min-height: calc(100vh - 140px);
                overflow-y: auto;
            }
            
            .main-content {
                flex-direction: column;
                height: auto;
            }
            
            .panel {
                min-height: 300px;
                max-height: none;
            }
            
            .visualization-panel {
                height: 400px;
                min-height: 400px;
            }
            
            .controls-panel, .output-panel {
                min-height: 400px;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .header {
                padding: 10px;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .authors {
                flex-direction: column;
                gap: 8px;
            }
            
            .course-info {
                order: -1;
                width: 100%;
            }
            
            .github-link {
                order: 2;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.2rem;
            }
            
            .subtitle {
                font-size: 0.8rem;
            }
            
            .panel {
                padding: 12px;
            }
            
            .panel-header h2 {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 8px;
                font-size: 0.85rem;
                min-width: 100px;
            }
            
            .output-tab {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .footer {
                padding: 10px;
            }
            
            .author, .course-info, .github-link {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.1rem;
                min-width: auto;
            }
            
            .subtitle {
                font-size: 0.75rem;
            }
            
            .container {
                padding: 8px;
            }
            
            .panel {
                padding: 10px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                min-width: auto;
            }
            
            .output-tabs {
                justify-content: center;
            }
            
            .output-tab {
                padding: 5px 8px;
                font-size: 0.75rem;
            }
            
            .visualization-panel {
                height: 350px;
                min-height: 350px;
            }
        }
        
        /* Mobile menu toggle for output panels */
        .mobile-menu-toggle {
            display: none;
            background: rgba(100, 255, 218, 0.1);
            border: 1px solid rgba(100, 255, 218, 0.3);
            color: #64ffda;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-bottom: 10px;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        
        @media (max-width: 991px) {
            .mobile-menu-toggle {
                display: flex;
            }
            
            .output-panel {
                max-height: 500px;
                transition: max-height 0.3s ease;
            }
            
            .output-panel.collapsed {
                max-height: 50px;
                overflow: hidden;
            }
        }
        
        .info-badge {
            display: inline-block;
            padding: 3px 6px;
            background: rgba(100, 255, 218, 0.1);
            border-radius: 4px;
            font-size: 0.75rem;
            color: #64ffda;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1><i class="fas fa-cogs"></i> 3D C Compiler Visualizer</h1>
            <div class="subtitle">Interactive Visualization of Compiler Pipeline Stages</div>
        </div>
        <div class="course-info">
            <span class="university">COMPUTER SCIENCE FINAL PROJECT</span>
        </div>
        <div class="user-info">
            <?php if (isLoggedIn()): ?>
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container">
        <div class="main-content">
            <!-- Left Panel - Controls -->
            <div class="panel controls-panel">
                <div class="panel-header">
                    <i class="fas fa-sliders-h"></i>
                    <h2>Compilation Controls</h2>
                </div>
                
                <div class="control-group">
                    <label for="view-mode"><i class="fas fa-eye"></i> View Mode:</label>
                    <select id="view-mode">
                        <option value="pipeline">Pipeline View</option>
                        <option value="ast">AST View</option>
                        <option value="cfg">Control Flow Graph</option>
                        <option value="memory">Memory Layout</option>
                    </select>
                </div>
                
                <div class="control-group">
                    <label for="stage-select"><i class="fas fa-code-branch"></i> Pipeline Stage:</label>
                    <select id="stage-select">
                        <option value="all">All Stages</option>
                        <option value="lexical">Lexical Analysis</option>
                        <option value="syntax">Syntax Analysis</option>
                        <option value="semantic">Semantic Analysis</option>
                        <option value="ir">IR Generation</option>
                        <option value="optimization">Optimization</option>
                        <option value="codegen">Code Generation</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" id="auto-rotate" checked>
                        <i class="fas fa-sync-alt"></i> Auto Rotate
                    </label>
                    <label>
                        <input type="checkbox" id="show-labels" checked>
                        <i class="fas fa-tag"></i> Show Labels
                    </label>
                    <label>
                        <input type="checkbox" id="show-errors" checked>
                        <i class="fas fa-exclamation-triangle"></i> Highlight Errors
                    </label>
                </div>
                
                <div class="button-group">
                    <button id="reset-view" class="btn">
                        <i class="fas fa-undo"></i> Reset View
                    </button>
                    <button id="screenshot" class="btn secondary">
                        <i class="fas fa-camera"></i> Screenshot
                    </button>
                </div>
                
                <div class="panel-header" style="margin-top: 20px;">
                    <i class="fas fa-code"></i>
                    <h2>Source Code Editor</h2>
                </div>
                
                <div class="code-editor">
                    <div class="code-editor-header">
                        <h3><i class="fas fa-file-code"></i> C Source Code</h3>
                        <select id="example-select" style="padding: 6px 10px; background: rgba(10,25,47,0.8); color: #e6f1ff; border-radius: 5px; border: 1px solid rgba(100,255,218,0.2); font-size: 0.85rem;">
                            <option value="">Load Example...</option>
                            <option value="simple">Simple Program</option>
                            <option value="conditional">Conditional Logic</option>
                            <option value="loop">Loop Example</option>
                            <option value="function">Function Example</option>
                        </select>
                    </div>
                    <textarea id="source-code" spellcheck="false">#include <stdio.h>

int main() {
    int x = 10;
    int y = 20;
    int result = x + y;
    
    printf("Result: %d\n", result);
    
    for (int i = 0; i < 5; i++) {
        printf("Iteration %d\n", i);
    }
    
    return 0;
}</textarea>
                    
                    <div class="button-group" style="margin-top: 15px;">
                        <button id="compile-btn" class="btn">
                            <i class="fas fa-play"></i> Compile & Visualize
                        </button>
                        <button id="reset-btn" class="btn danger">
                            <i class="fas fa-trash"></i> Reset
                        </button>
                    </div>
                </div>
                
                <div class="status-bar">
                    <div id="status-message">
                        <i class="fas fa-info-circle"></i> Ready to compile
                    </div>
                    <div class="progress-bar">
                        <div id="progress-bar" class="progress-fill"></div>
                    </div>
                </div>
            </div>
            
            <!-- Center Panel - Visualization -->
            <div class="panel visualization-panel">
                <div id="visualization-canvas"></div>
                <div class="visualization-controls">
                    <button id="zoom-in" class="icon-btn" title="Zoom In">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button id="zoom-out" class="icon-btn" title="Zoom Out">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button id="reset-camera" class="icon-btn" title="Reset Camera">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                </div>
            </div>
            
            <!-- Right Panel - Output -->
            <div class="panel output-panel" id="output-panel">
                <button class="mobile-menu-toggle" id="output-toggle">
                    <i class="fas fa-chevron-down"></i>
                    <span>Output Panel</span>
                </button>
                
                <div class="panel-header">
                    <i class="fas fa-terminal"></i>
                    <h2>Compilation Output</h2>
                </div>
                
                <!-- Output tabs -->
                <div class="output-tabs">
                    <button class="output-tab active" data-output="tokens">
                        <i class="fas fa-key"></i> <span class="tab-text">Tokens</span>
                    </button>
                    <button class="output-tab" data-output="ast">
                        <i class="fas fa-project-diagram"></i> <span class="tab-text">AST</span>
                    </button>
                    <button class="output-tab" data-output="ir">
                        <i class="fas fa-microchip"></i> <span class="tab-text">IR Code</span>
                    </button>
                    <button class="output-tab" data-output="asm">
                        <i class="fas fa-microchip"></i> <span class="tab-text">Assembly</span>
                    </button>
                    <button class="output-tab" data-output="errors" id="errors-tab" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i> <span class="tab-text">Errors</span>
                    </button>
                </div>
                
                <!-- Output content areas -->
                <div id="tokens-output" class="output-content active"></div>
                <div id="ast-output" class="output-content"></div>
                <div id="ir-output" class="output-content"></div>
                <div id="asm-output" class="output-content"></div>
                <div id="errors-output" class="output-content"></div>
                
                <div class="panel-header" style="margin-top: 20px;">
                    <i class="fas fa-info-circle"></i>
                    <h2>Stage Information</h2>
                </div>
                
                <div id="stage-info" class="stage-info">
                    <p><i class="fas fa-mouse-pointer"></i> Select a compilation stage or node to view details</p>
                    <div style="margin-top: 12px; padding: 12px; background: rgba(100,255,218,0.05); border-radius: 5px; border: 1px dashed rgba(100,255,218,0.2);">
                        <p style="color: #64ffda; margin-bottom: 6px; font-size: 0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Tip:</strong></p>
                        <p style="font-size: 0.8rem; color: #8892b0;">Hover over nodes in the 3D visualization to see detailed information. Click and drag to rotate the view.</p>
                    </div>
                </div>
                
                <div id="error-section" class="error-section" style="display: none;">
                    <h4><i class="fas fa-exclamation-triangle"></i> Errors Detected:</h4>
                    <div id="error-list"></div>
                </div>
                
                <div class="panel-header" style="margin-top: 20px;">
                    <i class="fas fa-download"></i>
                    <h2>Export Results</h2>
                </div>
                
                <div class="button-group">
                    <button id="download-ast" class="btn secondary">
                        <i class="fas fa-download"></i> <span class="btn-text">AST (JSON)</span>
                    </button>
                    <button id="download-asm" class="btn secondary">
                        <i class="fas fa-download"></i> <span class="btn-text">Assembly</span>
                    </button>
                    <button id="download-source" class="btn secondary">
                        <i class="fas fa-download"></i> <span class="btn-text">Source Code</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <div class="footer-content">
            <div class="course-info">
                <span>Final Year Project - Computer Science Department | Compiler Design & 3D Visualization System</span>
            </div>
            <div class="authors">
                <div class="author">
                    <i class="fas fa-user-graduate"></i>
                    <span>AGABA OLIVIER</span>
                </div>
                <div class="author">
                    <i class="fas fa-user-graduate"></i>
                    <span>IRADI ARINDA</span>
                </div>
            </div>
            <a href="https://github.com/Agabaofficial/c-compiler-3d-visualizer" target="_blank" class="github-link">
                <i class="fab fa-github"></i> View on GitHub
            </a>
        </div>
    </div>
    
    <div id="tooltip" class="tooltip"></div>

    <script>
        class CompilerVisualizer3D {
            constructor() {
                this.sessionId = null;
                this.currentView = 'pipeline';
                this.currentStage = 'all';
                this.autoRotate = true;
                this.showLabels = true;
                this.showErrors = true;
                this.scene = null;
                this.camera = null;
                this.renderer = null;
                this.controls = null;
                this.objects = [];
                this.animationId = null;
                this.totalErrors = 0;
                this.labels = [];
                this.isMobile = window.innerWidth < 992;
                
                this.init();
                this.bindEvents();
                this.animate();
                
                window.addEventListener('resize', () => this.handleResize());
            }
            
            init() {
                const container = document.getElementById('visualization-canvas');
                this.scene = new THREE.Scene();
                this.scene.background = new THREE.Color(0x0a192f);
                this.camera = new THREE.PerspectiveCamera(60, container.clientWidth / container.clientHeight, 0.1, 1000);
                this.camera.position.set(0, 30, 50);
                this.renderer = new THREE.WebGLRenderer({ antialias: true });
                this.renderer.setSize(container.clientWidth, container.clientHeight);
                this.renderer.shadowMap.enabled = true;
                this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
                container.appendChild(this.renderer.domElement);
                this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
                this.controls.enableDamping = true;
                this.controls.dampingFactor = 0.05;
                this.controls.minDistance = 10;
                this.controls.maxDistance = 200;
                const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
                this.scene.add(ambientLight);
                const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
                directionalLight.position.set(20, 40, 30);
                directionalLight.castShadow = true;
                directionalLight.shadow.mapSize.width = 1024;
                directionalLight.shadow.mapSize.height = 1024;
                this.scene.add(directionalLight);
                const gridHelper = new THREE.GridHelper(100, 20, 0x112240, 0x0a192f);
                gridHelper.position.y = -5;
                this.scene.add(gridHelper);
                this.raycaster = new THREE.Raycaster();
                this.mouse = new THREE.Vector2();
                container.addEventListener('mousemove', (e) => this.onMouseMove(e));
            }
            
            bindEvents() {
                document.getElementById('compile-btn').addEventListener('click', () => this.compile());
                document.getElementById('reset-btn').addEventListener('click', () => this.reset());
                document.getElementById('reset-view').addEventListener('click', () => this.resetView());
                document.getElementById('reset-camera').addEventListener('click', () => this.resetView());
                document.getElementById('screenshot').addEventListener('click', () => this.takeScreenshot());
                document.getElementById('zoom-in').addEventListener('click', () => this.zoom(1.2));
                document.getElementById('zoom-out').addEventListener('click', () => this.zoom(0.8));
                document.getElementById('output-toggle').addEventListener('click', () => this.toggleOutputPanel());
                document.getElementById('view-mode').addEventListener('change', (e) => { this.currentView = e.target.value; if (this.sessionId) this.visualize(); });
                document.getElementById('stage-select').addEventListener('change', (e) => { this.currentStage = e.target.value; if (this.sessionId) this.visualize(); });
                document.getElementById('auto-rotate').addEventListener('change', (e) => { this.autoRotate = e.target.checked; });
                document.getElementById('show-labels').addEventListener('change', (e) => { this.showLabels = e.target.checked; this.toggleLabels(); });
                document.getElementById('show-errors').addEventListener('change', (e) => { this.showErrors = e.target.checked; if (this.sessionId) this.visualize(); });
                document.getElementById('example-select').addEventListener('change', (e) => { this.loadExample(e.target.value); });
                document.querySelectorAll('.output-tab').forEach(tab => { tab.addEventListener('click', (e) => { const outputType = e.target.dataset.output || e.target.closest('.output-tab').dataset.output; this.showOutput(outputType); }); });
                document.getElementById('download-ast').addEventListener('click', () => this.download('ast'));
                document.getElementById('download-asm').addEventListener('click', () => this.download('asm'));
                document.getElementById('download-source').addEventListener('click', () => this.download('source'));
            }
            
            toggleOutputPanel() {
                const outputPanel = document.getElementById('output-panel');
                const toggleIcon = document.querySelector('#output-toggle i');
                const toggleText = document.querySelector('#output-toggle span');
                if (outputPanel.classList.contains('collapsed')) {
                    outputPanel.classList.remove('collapsed');
                    toggleIcon.className = 'fas fa-chevron-down';
                    toggleText.textContent = 'Output Panel';
                } else {
                    outputPanel.classList.add('collapsed');
                    toggleIcon.className = 'fas fa-chevron-up';
                    toggleText.textContent = 'Output Panel';
                }
            }
            
            async compile() {
                const sourceCode = document.getElementById('source-code').value;
                if (!sourceCode.trim()) { this.showStatus('Please enter source code', 'error'); return; }
                this.showStatus('Compiling...', 'info');
                this.updateProgress(10);
                try {
                    const response = await fetch('?api=compile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ source_code: sourceCode }) });
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const text = await response.text();
                    let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Invalid JSON response'); }
                    if (data.error) throw new Error(data.error);
                    this.sessionId = data.session_id;
                    this.totalErrors = data.errors?.length || 0;
                    this.updateProgress(50);
                    this.updateOutputs(data);
                    if (this.totalErrors > 0) { this.showOutput('errors'); document.getElementById('errors-tab').style.display = 'flex'; } else { this.showOutput('tokens'); }
                    await this.visualize();
                    this.updateProgress(100);
                    this.showStatus(data.success ? 'Compilation successful!' : 'Compilation completed with warnings', data.success ? 'success' : 'warning');
                } catch (error) { this.showStatus('Error: ' + error.message, 'error'); this.updateProgress(0); console.error(error); }
            }
            
            updateOutputs(data) {
                if (data.outputs && data.outputs.tokens) { const tokensText = data.outputs.tokens.map(t => `Line ${t.line}: ${t.type.padEnd(15)} = "${t.value}"`).join('\n'); document.getElementById('tokens-output').textContent = tokensText; }
                if (data.outputs && data.outputs.ast) { document.getElementById('ast-output').textContent = JSON.stringify(data.outputs.ast, null, 2); }
                if (data.outputs && data.outputs.ir) { const irText = data.outputs.ir.map((instr, idx) => { const parts = []; for (const [key, value] of Object.entries(instr)) if (key !== 'op') parts.push(`${key}: ${value}`); return `${idx.toString().padStart(3)}: ${instr.op.padEnd(8)}` + (parts.length ? ' [' + parts.join(', ') + ']' : ''); }).join('\n'); document.getElementById('ir-output').textContent = irText; }
                if (data.outputs && data.outputs.asm) { document.getElementById('asm-output').textContent = Array.isArray(data.outputs.asm) ? data.outputs.asm.join('\n') : data.outputs.asm; }
                if (data.errors && data.errors.length > 0) {
                    const errorsText = data.errors.map((err, idx) => `${idx+1}. ${err.type || 'Error'}:\n   ${err.message}\n   Line: ${err.line || 'N/A'}\n`).join('\n');
                    document.getElementById('errors-output').textContent = errorsText;
                    let errorHtml = '';
                    data.errors.forEach((error) => { const errorClass = error.severity === 'warning' ? 'error-warning' : ''; const icon = error.severity === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'; errorHtml += `<div class="error-item ${errorClass}"><div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;"><i class="fas ${icon}"></i> <strong>${error.type || 'Error'}:</strong> ${error.message}</div>${error.line ? `<div style="font-size:0.85rem; color:#ffd166;">Line ${error.line}</div>` : ''}</div>`; });
                    document.getElementById('error-list').innerHTML = errorHtml;
                    document.getElementById('error-section').style.display = 'block';
                } else { document.getElementById('error-section').style.display = 'none'; }
                if (data.stages && data.stages.length > 0) {
                    let stageHtml = '<h3 style="color:#64ffda; margin-bottom:12px; font-size:1rem;">Compilation Pipeline</h3>';
                    data.stages.forEach(stage => { const statusClass = `status-${stage.status || 'pending'}`; const icon = stage.status === 'completed' ? 'fa-check-circle' : stage.status === 'failed' ? 'fa-times-circle' : 'fa-clock'; stageHtml += `<div style="margin-bottom:10px; padding:12px; background:rgba(100,255,218,0.05); border-radius:6px; border:1px solid rgba(100,255,218,0.1);"><div style="display:flex; justify-content:space-between; align-items:center;"><div style="display:flex; align-items:center; gap:8px;"><i class="fas ${icon}" style="color:${stage.status === 'completed' ? '#64ffda' : stage.status === 'failed' ? '#ff6b6b' : '#8892b0'}"></i><strong style="font-size:0.9rem;">${stage.name}</strong></div><span class="stage-status ${statusClass}" style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:0.7rem; font-weight:bold; margin-left:8px; ${stage.status === 'completed' ? 'background:rgba(100,255,218,0.2); color:#64ffda' : stage.status === 'failed' ? 'background:rgba(255,107,107,0.2); color:#ff6b6b' : 'background:rgba(136,146,176,0.2); color:#8892b0'}">${stage.status || 'pending'}</span></div><div style="font-size:0.8rem; margin-top:6px; color:#8892b0;"><i class="far fa-clock"></i> Duration: ${stage.duration}ms${stage.errors && stage.errors.length > 0 ? `<br><i class="fas fa-exclamation-circle"></i> Errors: ${stage.errors.length}` : ''}</div></div>`; });
                    document.getElementById('stage-info').innerHTML = stageHtml;
                }
            }
            
            showOutput(outputType) {
                document.querySelectorAll('.output-tab').forEach(tab => { tab.classList.remove('active'); if (tab.dataset.output === outputType) tab.classList.add('active'); });
                document.querySelectorAll('.output-content').forEach(content => { content.classList.remove('active'); if (content.id === `${outputType}-output`) content.classList.add('active'); });
            }
            
            async visualize() {
                if (!this.sessionId) return;
                try {
                    const response = await fetch(`?api=visualize&session_id=${this.sessionId}&stage=${this.currentStage}&view=${this.currentView}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const text = await response.text();
                    let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Invalid JSON response'); }
                    if (data.error) throw new Error(data.error);
                    this.createVisualization(data);
                } catch (error) { console.error('Visualization error:', error); this.showStatus('Visualization error: ' + error.message, 'error'); }
            }
            
            createVisualization(data) {
                this.clearScene();
                if (!data.nodes || data.nodes.length === 0) { this.createDefaultVisualization(); return; }
                data.nodes.forEach(node => this.createNode(node));
                if (data.edges && data.edges.length > 0) data.edges.forEach(edge => this.createEdge(edge, data.nodes));
                if (this.showLabels) this.addLabels(data.nodes);
            }
            
            createDefaultVisualization() {
                const colors = [0x64ffda, 0x00d9a6, 0xff6b6b, 0xffa502, 0x9b59b6, 0x3498db];
                for (let i = 0; i < 6; i++) { const geometry = new THREE.SphereGeometry(3,32,32); const material = new THREE.MeshPhongMaterial({ color: colors[i], emissive: colors[i], emissiveIntensity:0.2, transparent:true, opacity:0.9 }); const sphere = new THREE.Mesh(geometry,material); sphere.position.set(i*12-30,0,0); sphere.castShadow=true; this.scene.add(sphere); this.objects.push(sphere); }
            }
            
            createNode(node) {
                let geometry, material; const color = new THREE.Color(node.color || '#64ffda');
                if (this.currentView === 'memory') geometry = new THREE.BoxGeometry(node.size||3, node.height||3, node.size||3);
                else if (node.type === 'entry' || node.type === 'exit') geometry = new THREE.ConeGeometry(node.size||2.5,5,16);
                else if (node.type === 'condition') geometry = new THREE.CylinderGeometry(node.size||2.5,node.size||2.5,4,16);
                else geometry = new THREE.SphereGeometry(node.size||2.5,32,32);
                material = new THREE.MeshPhongMaterial({ color:color, emissive:color, emissiveIntensity:0.2, transparent:true, opacity:0.9, shininess:100 });
                const mesh = new THREE.Mesh(geometry,material);
                mesh.position.set(node.position?.x||0, node.position?.y||0, node.position?.z||0);
                mesh.castShadow=true; mesh.receiveShadow=true; mesh.userData=node;
                this.scene.add(mesh); this.objects.push(mesh);
                if (node.has_errors && this.showErrors) this.addErrorIndicator(mesh);
                if (node.type === 'entry' || node.type === 'exit') this.addGlowEffect(mesh);
            }
            
            addErrorIndicator(mesh) { const node = mesh.userData; const geometry = new THREE.SphereGeometry((node.size||2.5)*1.3,16,16); const material = new THREE.MeshBasicMaterial({ color:0xff6b6b, transparent:true, opacity:0.3, side:THREE.DoubleSide }); const indicator = new THREE.Mesh(geometry,material); indicator.position.copy(mesh.position); this.scene.add(indicator); this.objects.push(indicator); }
            addGlowEffect(mesh) { const geometry = new THREE.SphereGeometry((mesh.userData.size||2.5)*1.5,16,16); const material = new THREE.MeshBasicMaterial({ color:0x64ffda, transparent:true, opacity:0.2, side:THREE.DoubleSide }); const glow = new THREE.Mesh(geometry,material); glow.position.copy(mesh.position); this.scene.add(glow); this.objects.push(glow); }
            
            createEdge(edge, nodes) {
                const fromNode = this.findObjectById(edge.from); const toNode = this.findObjectById(edge.to);
                if (!fromNode || !toNode) return;
                const curve = new THREE.CatmullRomCurve3([fromNode.position.clone(), new THREE.Vector3((fromNode.position.x+toNode.position.x)/2, (fromNode.position.y+toNode.position.y)/2+8, (fromNode.position.z+toNode.position.z)/2), toNode.position.clone()]);
                const geometry = new THREE.TubeGeometry(curve,20,0.2,8,false);
                const material = new THREE.MeshBasicMaterial({ color: this.getEdgeColor(edge.type), transparent:true, opacity:0.7 });
                const tube = new THREE.Mesh(geometry,material);
                this.scene.add(tube); this.objects.push(tube);
            }
            
            getEdgeColor(type) { const colors = { 'pipeline':0x64ffda, 'parent_child':0x00d9a6, 'control_flow':0xffa502, 'conditional':0xff6b6b, 'memory_adjacent':0x9b59b6 }; return colors[type]||0x8892b0; }
            
            addLabels(nodes) {
                this.labels.forEach(label => this.scene.remove(label)); this.labels = [];
                nodes.forEach(node => {
                    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d'); canvas.width=250; canvas.height=120;
                    const grad = ctx.createLinearGradient(0,0,canvas.width,0); grad.addColorStop(0,'rgba(10,25,47,0.9)'); grad.addColorStop(1,'rgba(17,34,64,0.9)'); ctx.fillStyle=grad; ctx.fillRect(0,0,canvas.width,canvas.height);
                    ctx.strokeStyle=node.color||'#64ffda'; ctx.lineWidth=2; ctx.strokeRect(1,1,canvas.width-2,canvas.height-2);
                    ctx.font='bold 14px "Inter", sans-serif'; ctx.fillStyle='#64ffda'; ctx.textAlign='center'; ctx.fillText(this.truncateText(node.name,20), canvas.width/2,30);
                    ctx.font='12px "Inter", sans-serif'; ctx.fillStyle='#8892b0'; ctx.fillText(`Type: ${node.type}`, canvas.width/2,55);
                    if (node.status) { ctx.font='11px "Inter", sans-serif'; ctx.fillStyle=node.status==='completed'?'#00d9a6':node.status==='failed'?'#ff6b6b':'#ffa502'; ctx.fillText(`Status: ${node.status}`, canvas.width/2,75); }
                    if (node.error_count>0) { ctx.fillStyle='#ff6b6b'; ctx.font='11px "Inter", sans-serif'; ctx.fillText(`${node.error_count} error(s)`, canvas.width/2,95); } else if (node.duration) { ctx.fillStyle='#8892b0'; ctx.font='11px "Inter", sans-serif'; ctx.fillText(`Duration: ${node.duration}ms`, canvas.width/2,95); }
                    const texture = new THREE.CanvasTexture(canvas); const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map:texture, transparent:true })); sprite.position.set(node.position?.x||0, (node.position?.y||0)+(node.height||node.size||2.5)+2, node.position?.z||0); sprite.scale.set(10,5,1); this.scene.add(sprite); this.labels.push(sprite);
                });
            }
            
            truncateText(text, maxLength) { return text.length<=maxLength?text:text.substring(0,maxLength)+'...'; }
            toggleLabels() { this.labels.forEach(label=>label.visible=this.showLabels); }
            findObjectById(id) { return this.objects.find(obj=>obj.userData?.id===id); }
            showStatus(message, type='info') { const statusEl = document.getElementById('status-message'); const icon = type==='error'?'fa-exclamation-circle':type==='warning'?'fa-exclamation-triangle':type==='success'?'fa-check-circle':'fa-info-circle'; statusEl.innerHTML=`<i class="fas ${icon}"></i> ${message}`; const progressBar=document.getElementById('progress-bar'); progressBar.className='progress-fill'; if(type==='error'){ statusEl.style.color='#ff6b6b'; progressBar.classList.add('error-indicator'); } else if(type==='warning') statusEl.style.color='#ffa502'; else if(type==='success') statusEl.style.color='#00d9a6'; else statusEl.style.color='#64ffda'; }
            updateProgress(percent) { document.getElementById('progress-bar').style.width=percent+'%'; }
            reset() { this.sessionId=null; this.totalErrors=0; document.getElementById('source-code').value=`#include <stdio.h>\n\nint main() {\n    int x = 10;\n    int y = 20;\n    int result = x + y;\n    \n    printf("Result: %d\\n", result);\n    \n    for (int i = 0; i < 5; i++) {\n        printf("Iteration %d\\n", i);\n    }\n    \n    return 0;\n}`; document.getElementById('tokens-output').textContent=''; document.getElementById('ast-output').textContent=''; document.getElementById('ir-output').textContent=''; document.getElementById('asm-output').textContent=''; document.getElementById('errors-output').textContent=''; this.showOutput('tokens'); document.getElementById('errors-tab').style.display='none'; document.getElementById('error-section').style.display='none'; document.getElementById('stage-info').innerHTML=`<p><i class="fas fa-mouse-pointer"></i> Select a compilation stage or node to view details</p><div style="margin-top:12px; padding:12px; background:rgba(100,255,218,0.05); border-radius:5px; border:1px dashed rgba(100,255,218,0.2);"><p style="color:#64ffda; margin-bottom:6px; font-size:0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Tip:</strong></p><p style="font-size:0.8rem; color:#8892b0;">Hover over nodes in the 3D visualization to see detailed information. Click and drag to rotate the view.</p></div>`; this.clearScene(); this.createDefaultVisualization(); this.updateProgress(0); this.showStatus('Ready to compile'); }
            clearScene() { this.objects.forEach(obj=>this.scene.remove(obj)); this.objects=[]; this.labels.forEach(label=>this.scene.remove(label)); this.labels=[]; }
            resetView() { this.camera.position.set(0,30,50); this.controls.reset(); }
            zoom(factor) { this.camera.position.multiplyScalar(factor); this.controls.update(); }
            takeScreenshot() { this.renderer.render(this.scene,this.camera); const link=document.createElement('a'); link.href=this.renderer.domElement.toDataURL('image/png'); link.download=`compiler_visualization_${Date.now()}.png`; link.click(); }
            async download(type) { if(!this.sessionId){ this.showStatus('No compilation session found','error'); return; } this.showStatus(`Downloading ${type}...`,'info'); try{ const response=await fetch(`?api=download&session_id=${this.sessionId}&type=${type}`); if(!response.ok) throw new Error('Download failed'); const blob=await response.blob(); const url=window.URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download=type==='asm'?`assembly_${this.sessionId}.asm`:type==='ast'?`ast_${this.sessionId}.json`:`source_${this.sessionId}.c`; document.body.appendChild(a); a.click(); document.body.removeChild(a); window.URL.revokeObjectURL(url); this.showStatus(`${type} downloaded successfully`,'success'); } catch(error){ this.showStatus('Download failed: '+error.message,'error'); } }
            loadExample(exampleId) { const examples={ simple:`#include <stdio.h>\n\nint main() {\n    int x = 10;\n    int y = 20;\n    int result = x + y;\n    printf("Result: %d\\n", result);\n    return 0;\n}`, conditional:`#include <stdio.h>\n\nint main() {\n    int score = 85;\n    \n    if (score >= 90) {\n        printf("Grade: A\\n");\n    } else if (score >= 80) {\n        printf("Grade: B\\n");\n    } else if (score >= 70) {\n        printf("Grade: C\\n");\n    } else {\n        printf("Grade: F\\n");\n    }\n    \n    return 0;\n}`, loop:`#include <stdio.h>\n\nint main() {\n    int numbers[5] = {1,2,3,4,5};\n    int sum = 0;\n    \n    for (int i = 0; i < 5; i++) {\n        sum += numbers[i];\n        printf("Adding %d, sum is now %d\\n", numbers[i], sum);\n    }\n    \n    printf("Total sum: %d\\n", sum);\n    return 0;\n}`, function:`#include <stdio.h>\n\nint multiply(int a, int b) { return a * b; }\n\nfloat calculateAverage(int arr[], int size) {\n    int sum = 0;\n    for (int i = 0; i < size; i++) sum += arr[i];\n    return (float)sum / size;\n}\n\nint main() {\n    int x = 5, y = 7;\n    int product = multiply(x, y);\n    printf("Product: %d\\n", product);\n    \n    int numbers[] = {10,20,30,40,50};\n    float average = calculateAverage(numbers, 5);\n    printf("Average: %.2f\\n", average);\n    \n    return 0;\n}` }; if(examples[exampleId]){ document.getElementById('source-code').value=examples[exampleId]; this.showStatus(`Loaded example: ${exampleId}`,'success'); } document.getElementById('example-select').value=''; }
            onMouseMove(event) { const rect=this.renderer.domElement.getBoundingClientRect(); this.mouse.x=((event.clientX-rect.left)/rect.width)*2-1; this.mouse.y=-((event.clientY-rect.top)/rect.height)*2+1; this.raycaster.setFromCamera(this.mouse,this.camera); const intersects=this.raycaster.intersectObjects(this.objects); const tooltip=document.getElementById('tooltip'); if(intersects.length>0){ const data=intersects[0].object.userData; if(data){ tooltip.style.display='block'; tooltip.style.left=(event.clientX+10)+'px'; tooltip.style.top=(event.clientY+10)+'px'; let html=`<div style="color:#64ffda; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">${this.truncateText(data.name,25)}</div>`; html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Type:</strong> ${data.type}</div>`; if(data.status) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Status:</strong> ${data.status}</div>`; if(data.duration) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Duration:</strong> ${data.duration}ms</div>`; if(data.error_count>0) html+=`<div style="color:#ff6b6b; margin-top:6px; font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> ${data.error_count} error(s)</div>`; tooltip.innerHTML=html; } } else { tooltip.style.display='none'; } }
            handleResize() { const container=document.getElementById('visualization-canvas'); this.camera.aspect=container.clientWidth/container.clientHeight; this.camera.updateProjectionMatrix(); this.renderer.setSize(container.clientWidth,container.clientHeight); this.isMobile=window.innerWidth<992; }
            animate() { this.animationId=requestAnimationFrame(()=>this.animate()); if(this.autoRotate) this.scene.rotation.y+=0.001; this.controls.update(); this.renderer.render(this.scene,this.camera); }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            window.visualizer = new CompilerVisualizer3D();
            if (window.innerWidth < 768) {
                document.querySelectorAll('.tab-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
                document.querySelectorAll('.btn-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
            }
        });
    </script>
</body>
</html>