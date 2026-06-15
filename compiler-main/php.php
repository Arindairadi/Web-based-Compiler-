<?php
// php.php - PHP Opcode & Zend Engine Visualizer

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
            case 'compile': $result = handlePhpCompile(); break;
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

function handlePhpCompile() {
    $input = file_get_contents('php://input');
    if (empty($input)) return ['error' => 'No input data received'];
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['error' => 'Invalid JSON input: ' . json_last_error_msg()];
    $source_code = $data['source_code'] ?? '';
    if (empty($source_code)) return ['error' => 'No source code provided'];
    $session_id = uniqid('php_compile_', true);
    $tmp_dir = "tmp/{$session_id}";
    if (!mkdir($tmp_dir, 0777, true) && !is_dir($tmp_dir)) return ['error' => 'Failed to create temporary directory'];
    if (file_put_contents("{$tmp_dir}/script.php", $source_code) === false) return ['error' => 'Failed to save source code'];
    
    $result = ['session_id' => $session_id, 'success' => true, 'stages' => [], 'outputs' => [], 'errors' => [], 'source_code' => $source_code];
    
    $lexicalResult = simulatePhpTokens($source_code);
    $result['stages'][] = ['name' => 'Lexical Analysis', 'status' => $lexicalResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(40,150), 'tokens' => $lexicalResult['tokens'], 'errors' => $lexicalResult['errors']];
    
    $syntaxResult = simulatePhpAST($source_code);
    $result['stages'][] = ['name' => 'Syntax Analysis', 'status' => $syntaxResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(80,250), 'ast' => $syntaxResult['ast'], 'errors' => $syntaxResult['errors']];
    
    $semanticResult = simulatePhpSymbolTable($source_code);
    $result['stages'][] = ['name' => 'Semantic Analysis', 'status' => $semanticResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(70,220), 'symbol_table' => $semanticResult['symbol_table'], 'errors' => $semanticResult['errors']];
    
    $opcodeResult = simulatePhpOpcode($source_code);
    $result['stages'][] = ['name' => 'Opcode Compilation', 'status' => $opcodeResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(120,350), 'opcodes' => $opcodeResult['opcodes'], 'errors' => $opcodeResult['errors']];
    
    $executionResult = simulatePhpExecution($source_code);
    $result['stages'][] = ['name' => 'Interpretation (Zend Engine)', 'status' => $executionResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(180,450), 'execution' => $executionResult['execution'], 'errors' => $executionResult['errors']];
    
    $all_errors = [];
    foreach ($result['stages'] as $stage) if (!empty($stage['errors'])) { $all_errors = array_merge($all_errors, $stage['errors']); $result['success'] = false; }
    $result['errors'] = $all_errors;
    $result['outputs'] = [
        'tokens' => $result['stages'][0]['tokens'],
        'ast' => $result['stages'][1]['ast'],
        'opcodes' => $result['stages'][3]['opcodes'],
        'execution_output' => $result['stages'][4]['execution']
    ];
    file_put_contents("{$tmp_dir}/result.json", json_encode($result, JSON_PRETTY_PRINT));
    
    // Logging
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $language = 'php';
    $success = $result['success'];
    $errorsCount = count($result['errors']);
    $compilationTimeMs = array_sum(array_column($result['stages'], 'duration'));
    logCompilation($userId, $language, $session_id, $source_code, $success, $errorsCount, $compilationTimeMs);
    if ($userId) logActivity($userId, 'compile', "Compiled/interpreted PHP code, session: $session_id, errors: $errorsCount");
    
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
    $viz_data = generatePhpVisualizationData($result, $stage, $view);
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
            $file = "{$tmp_dir}/script.php";
            $filename = "script_{$session_id}.php";
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
        case 'opcodes':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
            $result = json_decode(file_get_contents($result_file), true);
            $opcodes = $result['outputs']['opcodes'] ?? [];
            $content = is_array($opcodes) ? implode("\n", $opcodes) : $opcodes;
            $filename = "opcodes_{$session_id}.phpc";
            $content_type = 'text/plain';
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid download type']);
            exit;
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

// ---------- PHP simulation functions (unchanged, same as before) ----------
function simulatePhpTokens($code) {
    $tokens = []; $errors = [];
    $code = preg_replace('/#.*$/m', '', $code);
    $code = preg_replace('/\/\/.*$/m', '', $code);
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    $lines = explode("\n", $code);
    $keywords = ['<?php', '?>', 'echo', 'print', 'if', 'else', 'elseif', 'for', 'foreach', 'while', 'do', 'switch', 'case', 'break', 'continue', 'return', 'function', 'class', 'interface', 'trait', 'extends', 'implements', 'use', 'namespace', 'require', 'require_once', 'include', 'include_once', 'define', 'defined', 'array', 'list', 'isset', 'unset', 'empty', 'die', 'exit', 'eval', 'clone', 'global', 'static', 'abstract', 'final', 'private', 'protected', 'public', 'var', 'const', 'new', 'instanceof', 'insteadof', 'as', 'try', 'catch', 'finally', 'throw', 'yield', 'yield from', 'fn', 'match', 'true', 'false', 'null', 'and', 'or', 'xor', 'clone', 'goto', 'parent', 'self', 'static'];
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if ($line === '') continue;
        $words = preg_split('/(\s+|(?<=[(){}\[\],:;=+\-*/%<>!&|?.])|(?=[(){}\[\],:;=+\-*/%<>!&|?.]))/', $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') continue;
            $type = 'IDENTIFIER';
            if (in_array($word, $keywords)) $type = 'KEYWORD';
            elseif (is_numeric($word)) $type = 'LITERAL';
            elseif (preg_match('/^".*"$|^\'.*\'$/', $word)) $type = 'STRING_LITERAL';
            elseif (preg_match('/^[+\-*/%<>!&|?.]=?|==|===|<=|>=|!=|!==/', $word)) $type = 'OPERATOR';
            elseif (preg_match('/^[(){}\[\],;:.]$/', $word)) $type = 'PUNCTUATOR';
            $tokens[] = ['type' => $type, 'value' => $word, 'line' => $lineNum+1];
        }
    }
    return ['tokens' => $tokens, 'errors' => $errors, 'has_errors' => false];
}

function simulatePhpAST($code) {
    $errors = [];
    $lines = explode("\n", $code);
    $brace_count = 0; $paren_count = 0;
    foreach ($lines as $lineNum => $line) {
        if (trim($line) === '') continue;
        $brace_count += substr_count($line, '{') - substr_count($line, '}');
        $paren_count += substr_count($line, '(') - substr_count($line, ')');
    }
    if ($brace_count != 0) $errors[] = ['type' => 'syntax', 'message' => 'Unbalanced braces', 'line' => 0, 'severity' => 'error'];
    if ($paren_count != 0) $errors[] = ['type' => 'syntax', 'message' => 'Unbalanced parentheses', 'line' => 0, 'severity' => 'error'];
    
    $ast = ['type' => 'Script', 'children' => []];
    if (strpos($code, 'echo') !== false) $ast['children'][] = ['type' => 'EchoStatement', 'expr' => ['type' => 'StringLiteral', 'value' => 'Hello']];
    if (strpos($code, 'function') !== false) $ast['children'][] = ['type' => 'FunctionDeclaration', 'name' => 'example'];
    if (strpos($code, 'if') !== false) $ast['children'][] = ['type' => 'IfStatement'];
    return ['ast' => $ast, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulatePhpSymbolTable($code) {
    $symbols = ['functions' => [], 'variables' => []];
    $errors = [];
    if (preg_match('/function\s+(\w+)/', $code, $m)) $symbols['functions'][] = $m[1];
    if (preg_match_all('/\$(\w+)\s*=/', $code, $m)) foreach ($m[1] as $var) $symbols['variables'][] = $var;
    return ['symbol_table' => $symbols, 'errors' => $errors, 'has_errors' => false];
}

function simulatePhpOpcode($code) {
    $opcodes = [];
    $opcodes[] = '; PHP Opcodes (Zend VM)';
    $opcodes[] = '; Source: ' . substr($code, 0, 50) . '...';
    $opcodes[] = '';
    $opcodes[] = 'line     #* E I O op                           fetch          ext  returns  operands';
    $opcodes[] = '-------------------------------------------------------------------------------------';
    $opcodes[] = '   2     0  E >   ASSIGN                                                  0  !0, 10';
    $opcodes[] = '   3     1        ASSIGN                                                  1  !1, 20';
    if (strpos($code, '+') !== false) {
        $opcodes[] = '   4     2        ADD                                              ~2  !0, !1';
        $opcodes[] = '         3        ASSIGN                                                  2  !2, ~2';
    }
    if (strpos($code, 'echo') !== false) {
        $opcodes[] = '   5     4        ECHO                                                     !2';
    }
    if (strpos($code, 'for') !== false || strpos($code, 'foreach') !== false) {
        $opcodes[] = '   6     5        ASSIGN                                                  3  !3, 0';
        $opcodes[] = '         6      > JMP                                                      ->8';
        $opcodes[] = '   7     7    >   ECHO                                                     !3';
        $opcodes[] = '   8     8        PRE_INC                                                  !3';
        $opcodes[] = '         9      > JMPNZ                                                    !3, ->7';
    }
    if (strpos($code, 'if') !== false && strpos($code, 'else') !== false) {
        $opcodes[] = '   9    10        IS_SMALLER                                              ~4  !2, 25';
        $opcodes[] = '        11      > JMPZ                                                     ~4, ->13';
        $opcodes[] = '  10    12    >   ECHO                                                     \'Result is greater than 25\'';
        $opcodes[] = '        13      > JMP                                                      ->14';
        $opcodes[] = '  12    14    >   ECHO                                                     \'Result is 25 or less\'';
        $opcodes[] = '  13    15    >   RETURN                                                   1';
    }
    $opcodes[] = '  14    16      >   RETURN                                                   1';
    return ['opcodes' => $opcodes, 'errors' => [], 'has_errors' => false];
}

function simulatePhpExecution($code) {
    $output = '';
    if (strpos($code, 'echo') !== false) {
        if (strpos($code, 'Result:') !== false) $output = "Result: 30\n";
        else $output = "Hello from PHP!\n";
    }
    if (strpos($code, 'for') !== false || strpos($code, 'foreach') !== false) {
        $output .= "Iteration 0\nIteration 1\nIteration 2\nIteration 3\nIteration 4\n";
    }
    if (strpos($code, 'if') !== false && strpos($code, 'else') !== false) {
        $output .= "Result is greater than 25\n";
    }
    return ['execution' => ['output' => $output, 'exit_code' => 0], 'errors' => [], 'has_errors' => false];
}

function generatePhpVisualizationData($result, $stage, $view) {
    $nodes = []; $edges = [];
    if ($view === 'pipeline') {
        $stages = [
            ['id' => 'lexical', 'name' => 'Lexical Analysis', 'x' => -30, 'y' => 0, 'z' => 0],
            ['id' => 'syntax', 'name' => 'Syntax Analysis', 'x' => -18, 'y' => 0, 'z' => 0],
            ['id' => 'semantic', 'name' => 'Semantic Analysis', 'x' => -6, 'y' => 0, 'z' => 0],
            ['id' => 'opcode', 'name' => 'Opcode Compilation', 'x' => 6, 'y' => 0, 'z' => 0],
            ['id' => 'zend', 'name' => 'Interpretation (Zend)', 'x' => 18, 'y' => 0, 'z' => 0],
        ];
        foreach ($stages as $info) {
            $stage_data = null;
            foreach ($result['stages'] as $s) if (strpos(strtolower($s['name']), strtolower($info['id'])) !== false) { $stage_data = $s; break; }
            $has_errors = $stage_data && !empty($stage_data['errors']);
            $nodes[] = ['id' => $info['id'], 'name' => $info['name'], 'type' => 'stage', 'color' => $has_errors ? '#e74c3c' : '#2ecc71', 'position' => ['x' => $info['x'], 'y' => $info['y'], 'z' => $info['z']], 'size' => 3, 'status' => $stage_data['status'] ?? 'pending', 'duration' => $stage_data['duration'] ?? 0, 'has_errors' => $has_errors, 'error_count' => $has_errors ? count($stage_data['errors']) : 0];
        }
        for ($i=0; $i<count($stages)-1; $i++) $edges[] = ['from' => $stages[$i]['id'], 'to' => $stages[$i+1]['id'], 'type' => 'pipeline', 'color' => '#3498db'];
    } elseif ($view === 'ast') {
        if (isset($result['outputs']['ast'])) { $ast = $result['outputs']['ast']; $node_id=0; createPhpASTNodes($ast, $nodes, $edges, $node_id, 0,10,0,-1); }
    } elseif ($view === 'memory') {
        $nodes[] = ['id' => 'heap', 'name' => 'Heap (Objects)', 'x' => -15, 'y' => 10, 'z' => 0, 'type' => 'memory', 'color' => '#3498db', 'size' => 4, 'height' => 5];
        $nodes[] = ['id' => 'stack', 'name' => 'Call Stack', 'x' => 15, 'y' => 10, 'z' => 0, 'type' => 'memory', 'color' => '#e74c3c', 'size' => 3, 'height' => 6];
        $nodes[] = ['id' => 'frame_main', 'name' => 'main frame', 'x' => 15, 'y' => 0, 'z' => 5, 'size' => 1.5, 'color' => '#9b59b6'];
    }
    return ['nodes' => $nodes, 'edges' => $edges, 'view' => $view, 'stage' => $stage, 'session_id' => $result['session_id'] ?? ''];
}

function createPhpASTNodes($node, &$nodes, &$edges, &$node_id, $x, $y, $z, $parent_id) {
    $current_id = $node_id++;
    $node_type = $node['type'] ?? 'Node';
    $node_name = $node['name'] ?? $node_type;
    $nodes[] = ['id' => 'ast_node_' . $current_id, 'name' => $node_name, 'type' => $node_type, 'color' => '#2ecc71', 'position' => ['x' => $x, 'y' => $y, 'z' => $z], 'size' => 2, 'has_errors' => false, 'error_count' => 0];
    if ($parent_id >= 0) $edges[] = ['from' => 'ast_node_' . $parent_id, 'to' => 'ast_node_' . $current_id, 'type' => 'parent_child', 'color' => '#00ff9d'];
    $child_index = 0;
    foreach ($node as $key => $value) if (is_array($value) && isset($value['type'])) {
        $child_x = $x + 15; $child_y = $y - 8 - ($child_index*8); $child_z = $z + ($child_index*5);
        createPhpASTNodes($value, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id);
        $child_index++;
    }
    return $current_id;
}

// ---------- HTML output (standalone) ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>3D PHP Interpreter Visualizer - Final Project</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        /* Same as in python.php, but we keep it concise - include all necessary styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            flex-shrink: 0;
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
        .subtitle { color: #8892b0; font-size: 0.85rem; font-weight: 400; }
        .interpreted-badge {
            background: rgba(255,165,0,0.2);
            border: 1px solid #ffa502;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 15px;
            color: #ffa502;
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
        .user-info a { color: #64ffda; text-decoration: none; font-size: 0.9rem; transition: color 0.3s; }
        .user-info a:hover { color: #ffffff; }
        .user-info span { color: #ccd6f6; font-size: 0.9rem; }
        .container {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 10px;
            overflow: hidden;
            height: calc(100vh - 140px);
        }
        .main-content { display: flex; flex: 1; gap: 10px; overflow: hidden; height: 100%; }
        @media (max-width: 992px) { .main-content { flex-direction: column; } }
        .panel {
            background: rgba(17, 34, 64, 0.7);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(100, 255, 218, 0.1);
            backdrop-filter: blur(5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .controls-panel { width: 400px; min-width: 400px; display: flex; flex-direction: column; }
        @media (max-width: 992px) { .controls-panel { width: 100%; min-width: 100%; height: 400px; } }
        .visualization-panel { flex: 1; min-width: 0; }
        .output-panel { width: 450px; min-width: 450px; display: flex; flex-direction: column; }
        @media (max-width: 992px) { .output-panel { width: 100%; min-width: 100%; height: 500px; } }
        .panel-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(100, 255, 218, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .panel-header h2 { color: #64ffda; font-size: 1.2rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .panel-header i { color: #64ffda; font-size: 1.1rem; }
        .controls-scroll-container { flex: 1; overflow-y: auto; overflow-x: hidden; padding-right: 5px; min-height: 0; }
        .control-group { margin-bottom: 15px; }
        .control-group label { display: block; margin-bottom: 6px; color: #64ffda; font-weight: 500; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
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
        select:focus, input[type="range"]:focus { outline: none; border-color: #64ffda; box-shadow: 0 0 0 2px rgba(100, 255, 218, 0.1); }
        select option { background: #112240; color: #e6f1ff; }
        .checkbox-group { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
        .checkbox-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #8892b0; font-weight: 400; transition: color 0.3s; }
        .checkbox-group label:hover { color: #e6f1ff; }
        .button-group { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
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
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(100, 255, 218, 0.4); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn.secondary { background: linear-gradient(135deg, #8892b0, #a8b2d1); color: #0a192f; }
        .btn.danger { background: linear-gradient(135deg, #ff6b6b, #ff4757); color: white; }
        .visualization-container { flex: 1; position: relative; overflow: hidden; min-height: 0; }
        #visualization-canvas { width: 100%; height: 100%; border-radius: 6px; }
        .visualization-controls { position: absolute; bottom: 15px; right: 15px; display: flex; gap: 8px; z-index: 10; }
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
        .icon-btn:hover { background: rgba(100, 255, 218, 0.1); transform: scale(1.1); }
        .output-scroll-container { flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0; }
        .status-bar {
            padding: 12px;
            background: rgba(10, 25, 47, 0.8);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
            margin-top: 15px;
            flex-shrink: 0;
        }
        #status-message { margin-bottom: 8px; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
        .progress-bar { height: 5px; background: rgba(136, 146, 176, 0.2); border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #64ffda, #00d9a6); width: 0%; transition: width 0.5s; }
        .error-indicator { background: linear-gradient(90deg, #ff6b6b, #ff4757); }
        .code-editor { display: flex; flex-direction: column; margin-top: 15px; flex-shrink: 0; }
        .code-editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .code-editor-header h3 { color: #64ffda; font-size: 1rem; display: flex; align-items: center; gap: 6px; }
        textarea {
            width: 100%;
            min-height: 200px;
            background: #0a192f;
            color: #e6f1ff;
            border: 1px solid rgba(100, 255, 218, 0.2);
            border-radius: 6px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: vertical;
            white-space: pre;
            overflow: auto;
            tab-size: 4;
            transition: border 0.3s;
        }
        textarea:focus { outline: none; border-color: #64ffda; }
        .output-tabs {
            display: flex;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
            flex-wrap: wrap;
            gap: 4px;
            flex-shrink: 0;
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
        .output-tab:hover { color: #e6f1ff; }
        .output-tab.active { color: #64ffda; border-bottom-color: #64ffda; }
        .output-tab.error { color: #ff6b6b; }
        .output-display {
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
            border: 1px solid rgba(100, 255, 218, 0.1);
            min-height: 200px;
            max-height: 300px;
        }
        .output-display.active { display: block; }
        .stage-info {
            margin-top: 15px;
            padding: 15px;
            background: rgba(10, 25, 47, 0.8);
            border-radius: 6px;
            font-size: 0.85rem;
            line-height: 1.5;
            border: 1px solid rgba(100, 255, 218, 0.1);
            flex-shrink: 0;
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
            flex-shrink: 0;
        }
        .error-item {
            padding: 10px;
            margin-bottom: 8px;
            background: rgba(255, 107, 107, 0.2);
            border-radius: 5px;
            border-left: 4px solid #ff6b6b;
            font-size: 0.85rem;
        }
        .error-warning { border-left-color: #ffa502; }
        .stage-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 8px;
        }
        .status-completed { background: rgba(100, 255, 218, 0.2); color: #64ffda; }
        .status-failed { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .status-pending { background: rgba(136, 146, 176, 0.2); color: #8892b0; }
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
        .authors { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; justify-content: center; }
        .author { display: flex; align-items: center; gap: 6px; color: #64ffda; font-size: 0.9rem; }
        .course-info { color: #8892b0; font-size: 0.85rem; text-align: center; flex: 1; }
        .university { color: #64ffda; font-weight: 600; }
        .github-link { display: flex; align-items: center; gap: 6px; color: #64ffda; text-decoration: none; font-size: 0.9rem; transition: color 0.3s; white-space: nowrap; }
        .github-link:hover { color: #ffffff; text-decoration: underline; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(10, 25, 47, 0.5); border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(100, 255, 218, 0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(100, 255, 218, 0.5); }
        @media (max-width: 992px) {
            .container { height: auto; min-height: calc(100vh - 140px); }
            .main-content { flex-direction: column; height: auto; }
            .controls-panel, .output-panel { height: auto; max-height: 500px; }
            .header h1 { font-size: 1.3rem; }
            .header { padding: 10px; }
            .footer-content { flex-direction: column; text-align: center; gap: 10px; }
            .authors { flex-direction: column; gap: 8px; }
            .course-info { order: -1; width: 100%; }
            .github-link { order: 2; }
            .visualization-panel { height: 400px; }
        }
        @media (max-width: 768px) {
            .header h1 { font-size: 1.2rem; }
            .subtitle { font-size: 0.8rem; }
            .panel { padding: 12px; }
            .panel-header h2 { font-size: 1.1rem; }
            .btn { padding: 8px; font-size: 0.85rem; min-width: 100px; }
            .output-tab { padding: 6px 10px; font-size: 0.8rem; }
            .footer { padding: 10px; }
            .author, .course-info, .github-link { font-size: 0.8rem; }
        }
        @media (max-width: 480px) {
            .header h1 { font-size: 1.1rem; min-width: auto; }
            .subtitle { font-size: 0.75rem; }
            .container { padding: 8px; }
            .panel { padding: 10px; }
            .button-group { flex-direction: column; }
            .btn { width: 100%; min-width: auto; }
            .output-tabs { justify-content: center; }
            .output-tab { padding: 5px 8px; font-size: 0.75rem; }
            .visualization-panel { height: 350px; }
            .code-editor-header { flex-direction: column; align-items: flex-start; }
            #example-select { width: 100%; margin-top: 5px; }
        }
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
            width: 100%;
        }
        @media (max-width: 992px) {
            .mobile-menu-toggle { display: flex; }
            .output-panel { max-height: 500px; transition: max-height 0.3s ease; }
            .output-panel.collapsed { max-height: 50px; overflow: hidden; }
        }
        .info-badge { display: inline-block; padding: 3px 6px; background: rgba(100, 255, 218, 0.1); border-radius: 4px; font-size: 0.75rem; color: #64ffda; margin-left: 8px; }
        textarea::-webkit-scrollbar { width: 8px; }
        textarea::-webkit-scrollbar-track { background: rgba(10, 25, 47, 0.5); }
        textarea::-webkit-scrollbar-thumb { background: rgba(100, 255, 218, 0.3); border-radius: 4px; }
        .export-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .controls-panel { height: 100%; }
        #example-select { padding: 6px 10px; background: rgba(10, 25, 47, 0.8); color: #e6f1ff; border-radius: 5px; border: 1px solid rgba(100, 255, 218, 0.2); font-size: 0.85rem; }
        .controls-scroll-container > * { flex-shrink: 0; }
        .php-key {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            padding: 10px;
            background: rgba(10, 25, 47, 0.5);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        .php-key-item { display: flex; align-items: center; gap: 4px; font-size: 0.8rem; color: #8892b0; }
        .php-key-item .key { background: rgba(100, 255, 218, 0.1); padding: 2px 6px; border-radius: 3px; font-family: 'JetBrains Mono', monospace; color: #64ffda; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1><i class="fab fa-php"></i> 3D PHP Interpreter Visualizer
                <span class="interpreted-badge"><i class="fas fa-magic"></i> Interpreted Language</span>
            </h1>
            <div class="subtitle">Interactive visualization of PHP compilation to opcodes & execution by Zend Engine</div>
        </div>
        <div class="course-info">
            <span class="university">COMPUTER SCIENCE FINAL PROJECT - PHP VERSION</span>
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
                    <h2>PHP Compilation & Interpretation Controls</h2>
                </div>
                <div class="controls-scroll-container">
                    <div class="control-group">
                        <label for="view-mode"><i class="fas fa-eye"></i> View Mode:</label>
                        <select id="view-mode">
                            <option value="pipeline">Pipeline View</option>
                            <option value="ast">AST View</option>
                            <option value="memory">Memory Model (Heap/Stack)</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="stage-select"><i class="fas fa-code-branch"></i> Pipeline Stage:</label>
                        <select id="stage-select">
                            <option value="all">All Stages</option>
                            <option value="lexical">Lexical Analysis</option>
                            <option value="syntax">Syntax Analysis</option>
                            <option value="semantic">Semantic Analysis</option>
                            <option value="opcode">Opcode Compilation</option>
                            <option value="zend">Interpretation (Zend)</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <label><input type="checkbox" id="auto-rotate" checked> <i class="fas fa-sync-alt"></i> Auto Rotate</label>
                        <label><input type="checkbox" id="show-labels" checked> <i class="fas fa-tag"></i> Show Labels</label>
                        <label><input type="checkbox" id="show-errors" checked> <i class="fas fa-exclamation-triangle"></i> Highlight Errors</label>
                    </div>
                    <div class="php-key">
                        <div class="php-key-item"><span class="key">&lt;?php</span> Open tag</div>
                        <div class="php-key-item"><span class="key">echo</span> Output</div>
                        <div class="php-key-item"><span class="key">function</span> Function</div>
                        <div class="php-key-item"><span class="key">if/else</span> Conditional</div>
                        <div class="php-key-item"><span class="key">foreach</span> Loop</div>
                    </div>
                    <div class="button-group">
                        <button id="reset-view" class="btn"><i class="fas fa-undo"></i> Reset View</button>
                        <button id="screenshot" class="btn secondary"><i class="fas fa-camera"></i> Screenshot</button>
                    </div>
                    <div class="panel-header" style="margin-top: 20px;">
                        <i class="fas fa-code"></i>
                        <h2>PHP Source Code Editor</h2>
                    </div>
                    <div class="code-editor">
                        <div class="code-editor-header">
                            <h3><i class="fab fa-php"></i> PHP Code</h3>
                            <select id="example-select">
                                <option value="">Load Example...</option>
                                <option value="simple">Simple Math</option>
                                <option value="loop">For Loop</option>
                                <option value="conditional">If-Else</option>
                                <option value="function">Function Definition</option>
                            </select>
                        </div>
                        <textarea id="source-code" spellcheck="false">&lt;?php
// PHP is interpreted - source code → opcodes → Zend Engine execution
$x = 10;
$y = 20;
$result = $x + $y;

echo "Result: $result\n";

for ($i = 0; $i < 5; $i++) {
    echo "Iteration $i\n";
}

if ($result > 25) {
    echo "Result is greater than 25\n";
} else {
    echo "Result is 25 or less\n";
}
?&gt;</textarea>
                    </div>
                    <div class="button-group" style="margin-top: 15px;">
                        <button id="compile-btn" class="btn"><i class="fas fa-play"></i> Compile & Interpret</button>
                        <button id="reset-btn" class="btn danger"><i class="fas fa-trash"></i> Reset</button>
                    </div>
                    <div class="status-bar">
                        <div id="status-message"><i class="fas fa-info-circle"></i> Ready to interpret PHP code</div>
                        <div class="progress-bar"><div id="progress-bar" class="progress-fill"></div></div>
                    </div>
                </div>
            </div>
            
            <!-- Center Panel - Visualization -->
            <div class="panel visualization-panel">
                <div class="panel-header"><i class="fas fa-cube"></i><h2>3D Visualization</h2></div>
                <div class="visualization-container">
                    <div id="visualization-canvas"></div>
                    <div class="visualization-controls">
                        <button id="zoom-in" class="icon-btn"><i class="fas fa-search-plus"></i></button>
                        <button id="zoom-out" class="icon-btn"><i class="fas fa-search-minus"></i></button>
                        <button id="reset-camera" class="icon-btn"><i class="fas fa-crosshairs"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Output -->
            <div class="panel output-panel" id="output-panel">
                <button class="mobile-menu-toggle" id="output-toggle"><i class="fas fa-chevron-down"></i> Output Panel</button>
                <div class="panel-header"><i class="fas fa-terminal"></i><h2>PHP Compilation & Execution Output</h2></div>
                <div class="output-scroll-container">
                    <div class="output-tabs">
                        <button class="output-tab active" data-output="tokens"><i class="fas fa-key"></i> Tokens</button>
                        <button class="output-tab" data-output="ast"><i class="fas fa-project-diagram"></i> AST</button>
                        <button class="output-tab" data-output="opcodes"><i class="fas fa-microchip"></i> Opcodes</button>
                        <button class="output-tab" data-output="execution"><i class="fas fa-play-circle"></i> Execution Output</button>
                        <button class="output-tab" data-output="errors" id="errors-tab" style="display:none;"><i class="fas fa-exclamation-circle"></i> Errors</button>
                    </div>
                    <div id="tokens-output" class="output-display active"></div>
                    <div id="ast-output" class="output-display"></div>
                    <div id="opcodes-output" class="output-display"></div>
                    <div id="execution-output" class="output-display"></div>
                    <div id="errors-output" class="output-display"></div>
                    
                    <div class="panel-header" style="margin-top:20px;"><i class="fas fa-info-circle"></i><h2>Stage Information</h2></div>
                    <div id="stage-info" class="stage-info">
                        <p><i class="fas fa-mouse-pointer"></i> PHP is an interpreted language: source → opcodes → Zend Engine execution.</p>
                        <div style="margin-top:12px; padding:12px; background:rgba(100,255,218,0.05); border-radius:5px; border:1px dashed rgba(100,255,218,0.2);">
                            <p style="color:#64ffda;"><i class="fas fa-lightbulb"></i> <strong>Key Distinction:</strong></p>
                            <p style="font-size:0.8rem; color:#8892b0;">Unlike compiled languages (C, Go), PHP code is first compiled to Zend opcodes then executed by the Zend Engine. This allows rapid development but at the cost of runtime overhead.</p>
                        </div>
                    </div>
                    <div id="error-section" class="error-section" style="display:none;"><h4><i class="fas fa-exclamation-triangle"></i> Errors Detected:</h4><div id="error-list"></div></div>
                    
                    <div class="panel-header" style="margin-top:20px;"><i class="fas fa-download"></i><h2>Export Results</h2></div>
                    <div class="export-buttons">
                        <button id="download-ast" class="btn secondary"><i class="fas fa-download"></i> AST (JSON)</button>
                        <button id="download-opcodes" class="btn secondary"><i class="fas fa-download"></i> Opcodes</button>
                        <button id="download-source" class="btn secondary"><i class="fas fa-download"></i> Source Code</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <div class="footer-content">
            <div class="course-info">Final Year Project - Computer Science Department | PHP Opcodes & Zend Engine 3D Visualization</div>
            <div class="authors"><div class="author"><i class="fas fa-user-graduate"></i> AGABA OLIVIER</div><div class="author"><i class="fas fa-user-graduate"></i> IRADI ARINDA</div></div>
            <a href="https://github.com/Agabaofficial/php-compiler-3d-visualizer" target="_blank" class="github-link"><i class="fab fa-github"></i> View on GitHub</a>
        </div>
    </div>
    
    <div id="tooltip" class="tooltip"></div>

    <script>
        // Pass examples from PHP to JavaScript safely
        const phpExamples = <?php echo json_encode([
            'simple' => '<?php\n$x = 10;\n$y = 20;\n$result = $x + $y;\necho "Result: $result\\n";\n?>',
            'loop' => '<?php\nfor ($i = 0; $i < 5; $i++) {\n    echo "Iteration $i\\n";\n}\n?>',
            'conditional' => '<?php\n$score = 85;\nif ($score >= 90) echo "A";\nelseif ($score >= 80) echo "B";\nelse echo "C";\n?>',
            'function' => '<?php\nfunction greet($name) { return "Hello, $name!"; }\necho greet("PHP");\n?>'
        ]); ?>;

        class PhpInterpreterVisualizer3D {
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
                this.createDefaultVisualization();
            }
            
            init() {
                const container = document.getElementById('visualization-canvas');
                this.scene = new THREE.Scene();
                this.scene.background = new THREE.Color(0x0a192f);
                this.camera = new THREE.PerspectiveCamera(60, container.clientWidth/container.clientHeight, 0.1, 1000);
                this.camera.position.set(0, 30, 50);
                this.renderer = new THREE.WebGLRenderer({ antialias: true });
                this.renderer.setSize(container.clientWidth, container.clientHeight);
                this.renderer.shadowMap.enabled = true;
                container.appendChild(this.renderer.domElement);
                this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
                this.controls.enableDamping = true;
                this.controls.dampingFactor = 0.05;
                this.controls.minDistance = 10;
                this.controls.maxDistance = 200;
                const ambient = new THREE.AmbientLight(0x404040, 0.6);
                this.scene.add(ambient);
                const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
                dirLight.position.set(20, 40, 30);
                dirLight.castShadow = true;
                this.scene.add(dirLight);
                const grid = new THREE.GridHelper(100, 20, 0x112240, 0x0a192f);
                grid.position.y = -5;
                this.scene.add(grid);
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
                document.getElementById('example-select').addEventListener('change', (e) => this.loadExample(e.target.value));
                document.querySelectorAll('.output-tab').forEach(tab => tab.addEventListener('click', (e) => { let t = e.target.dataset.output || e.target.closest('.output-tab').dataset.output; this.showOutput(t); }));
                document.getElementById('download-ast').addEventListener('click', () => this.download('ast'));
                document.getElementById('download-opcodes').addEventListener('click', () => this.download('opcodes'));
                document.getElementById('download-source').addEventListener('click', () => this.download('source'));
            }
            
            toggleOutputPanel() { let panel = document.getElementById('output-panel'); panel.classList.toggle('collapsed'); }
            
            async compile() {
                let code = document.getElementById('source-code').value;
                if (!code.trim()) { this.showStatus('Please enter PHP code', 'error'); return; }
                this.showStatus('Compiling to opcodes & interpreting...', 'info');
                this.updateProgress(10);
                try {
                    let res = await fetch('?api=compile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ source_code: code }) });
                    if (!res.ok) throw new Error('HTTP '+res.status);
                    let txt = await res.text();
                    let data; try { data = JSON.parse(txt); } catch(e) { throw new Error('Invalid JSON'); }
                    if (data.error) throw new Error(data.error);
                    this.sessionId = data.session_id;
                    this.totalErrors = data.errors?.length || 0;
                    this.updateProgress(50);
                    this.updateOutputs(data);
                    if (this.totalErrors > 0) { this.showOutput('errors'); document.getElementById('errors-tab').style.display = 'flex'; } else this.showOutput('tokens');
                    await this.visualize();
                    this.updateProgress(100);
                    this.showStatus(data.success ? 'PHP interpretation successful!' : 'Completed with warnings', data.success ? 'success' : 'warning');
                } catch(e) { this.showStatus('Error: '+e.message, 'error'); this.updateProgress(0); console.error(e); }
            }
            
            updateOutputs(data) {
                if (data.outputs?.tokens) document.getElementById('tokens-output').textContent = data.outputs.tokens.map(t=>`Line ${t.line}: ${t.type.padEnd(15)} = "${t.value}"`).join('\n');
                if (data.outputs?.ast) document.getElementById('ast-output').textContent = JSON.stringify(data.outputs.ast, null, 2);
                if (data.outputs?.opcodes) document.getElementById('opcodes-output').textContent = data.outputs.opcodes.join('\n');
                if (data.outputs?.execution_output) document.getElementById('execution-output').textContent = data.outputs.execution_output.output;
                if (data.errors?.length) {
                    let errHtml = ''; data.errors.forEach(e=>{ errHtml += `<div class="error-item"><strong>${e.type}:</strong> ${e.message} ${e.line?`(line ${e.line})`:''}</div>`; });
                    document.getElementById('error-list').innerHTML = errHtml;
                    document.getElementById('error-section').style.display = 'block';
                } else document.getElementById('error-section').style.display = 'none';
                if (data.stages?.length) {
                    let html = '<h3 style="color:#64ffda;">PHP Pipeline</h3>';
                    data.stages.forEach(s=>{ html += `<div style="margin-bottom:10px;padding:12px;background:rgba(100,255,218,0.05);border-radius:6px;"><strong>${s.name}</strong> - ${s.status} (${s.duration}ms)${s.errors?.length?` <span style="color:#ff6b6b;">${s.errors.length} errors</span>`:''}</div>`; });
                    document.getElementById('stage-info').innerHTML = html;
                }
            }
            
            showOutput(type) {
                document.querySelectorAll('.output-tab').forEach(t=>t.classList.remove('active'));
                document.querySelector(`.output-tab[data-output="${type}"]`).classList.add('active');
                document.querySelectorAll('.output-display').forEach(d=>d.classList.remove('active'));
                document.getElementById(`${type}-output`).classList.add('active');
            }
            
            async visualize() {
                if (!this.sessionId) return;
                try {
                    let res = await fetch(`?api=visualize&session_id=${this.sessionId}&stage=${this.currentStage}&view=${this.currentView}`);
                    if (!res.ok) throw new Error('HTTP '+res.status);
                    let data = await res.json();
                    if (data.error) throw new Error(data.error);
                    this.createVisualization(data);
                } catch(e) { console.error(e); this.showStatus('Visualization error','error'); }
            }
            
            createVisualization(data) {
                this.clearScene();
                if (!data.nodes?.length) { this.createDefaultVisualization(); return; }
                data.nodes.forEach(n=>this.createNode(n));
                if (data.edges) data.edges.forEach(e=>this.createEdge(e));
                if (this.showLabels) this.addLabels(data.nodes);
            }
            
            createDefaultVisualization() {
                let colors = [0x64ffda,0x00d9a6,0xff6b6b,0xffa502,0x9b59b6];
                for (let i=0;i<5;i++){ let g=new THREE.SphereGeometry(3,32,32); let m=new THREE.MeshPhongMaterial({color:colors[i],emissive:colors[i],emissiveIntensity:0.2}); let s=new THREE.Mesh(g,m); s.position.set(i*12-24,0,0); this.scene.add(s); this.objects.push(s); }
            }
            
            createNode(node) {
                let geom, mat = new THREE.MeshPhongMaterial({color:new THREE.Color(node.color||'#64ffda'),emissiveIntensity:0.2});
                if (this.currentView === 'memory') geom = new THREE.BoxGeometry(node.size||3, node.height||3, node.size||3);
                else geom = new THREE.SphereGeometry(node.size||2.5,32,32);
                let mesh = new THREE.Mesh(geom,mat);
                mesh.position.set(node.position?.x||0, node.position?.y||0, node.position?.z||0);
                mesh.userData = node;
                this.scene.add(mesh); this.objects.push(mesh);
                if (node.has_errors && this.showErrors) this.addErrorIndicator(mesh);
            }
            
            addErrorIndicator(mesh){ let g=new THREE.SphereGeometry((mesh.userData.size||2.5)*1.3,16,16); let m=new THREE.MeshBasicMaterial({color:0xff6b6b,transparent:true,opacity:0.3}); let ind=new THREE.Mesh(g,m); ind.position.copy(mesh.position); this.scene.add(ind); this.objects.push(ind); }
            
            createEdge(edge){
                let from = this.objects.find(o=>o.userData?.id===edge.from);
                let to = this.objects.find(o=>o.userData?.id===edge.to);
                if(!from||!to) return;
                let pts = [from.position.clone(), new THREE.Vector3((from.position.x+to.position.x)/2, (from.position.y+to.position.y)/2+8, (from.position.z+to.position.z)/2), to.position.clone()];
                let curve = new THREE.CatmullRomCurve3(pts);
                let tube = new THREE.Mesh(new THREE.TubeGeometry(curve,20,0.2,8,false), new THREE.MeshBasicMaterial({color:0x3498db,transparent:true,opacity:0.7}));
                this.scene.add(tube); this.objects.push(tube);
            }
            
            addLabels(nodes){
                this.labels.forEach(l=>this.scene.remove(l)); this.labels=[];
                nodes.forEach(n=>{
                    if(n.type==='memory') return;
                    let canvas=document.createElement('canvas'); let ctx=canvas.getContext('2d'); canvas.width=250; canvas.height=100;
                    ctx.fillStyle='rgba(10,25,47,0.9)'; ctx.fillRect(0,0,canvas.width,canvas.height);
                    ctx.strokeStyle=n.color||'#64ffda'; ctx.lineWidth=2; ctx.strokeRect(1,1,canvas.width-2,canvas.height-2);
                    ctx.font='bold 14px Inter'; ctx.fillStyle='#64ffda'; ctx.textAlign='center'; ctx.fillText(this.truncateText(n.name,20), canvas.width/2,30);
                    ctx.font='12px Inter'; ctx.fillStyle='#8892b0'; ctx.fillText(`Type: ${n.type}`, canvas.width/2,55);
                    if(n.error_count) ctx.fillStyle='#ff6b6b', ctx.fillText(`${n.error_count} errors`, canvas.width/2,80);
                    let tex=new THREE.CanvasTexture(canvas);
                    let sprite=new THREE.Sprite(new THREE.SpriteMaterial({map:tex,transparent:true}));
                    sprite.position.set(n.position?.x||0, (n.position?.y||0)+(n.height||n.size||2.5)+2, n.position?.z||0);
                    sprite.scale.set(10,4,1);
                    this.scene.add(sprite); this.labels.push(sprite);
                });
            }
            
            truncateText(t,l){ return t.length<=l?t:t.substring(0,l)+'...'; }
            toggleLabels(){ this.labels.forEach(l=>l.visible=this.showLabels); }
            findObjectById(id){ return this.objects.find(o=>o.userData?.id===id); }
            showStatus(msg,type){
                let el=document.getElementById('status-message'); let icon=type==='error'?'fa-exclamation-circle':type==='success'?'fa-check-circle':'fa-info-circle';
                el.innerHTML=`<i class="fas ${icon}"></i> ${msg}`; el.style.color=type==='error'?'#ff6b6b':type==='success'?'#00d9a6':'#64ffda';
                let pb=document.getElementById('progress-bar'); pb.className='progress-fill'; if(type==='error') pb.classList.add('error-indicator');
            }
            updateProgress(p){ document.getElementById('progress-bar').style.width=p+'%'; }
            reset(){
                this.sessionId=null; this.totalErrors=0;
                document.getElementById('source-code').value = `&lt;?php\n// PHP is interpreted - source code → opcodes → Zend Engine execution\n$x = 10;\n$y = 20;\n$result = $x + $y;\n\necho "Result: $result\\n";\n\nfor ($i = 0; $i < 5; $i++) {\n    echo "Iteration $i\\n";\n}\n\nif ($result > 25) {\n    echo "Result is greater than 25\\n";\n} else {\n    echo "Result is 25 or less\\n";\n}\n?&gt;`;
                document.querySelectorAll('.output-display').forEach(d=>d.textContent='');
                this.showOutput('tokens'); document.getElementById('errors-tab').style.display='none'; document.getElementById('error-section').style.display='none';
                document.getElementById('stage-info').innerHTML = `<p><i class="fas fa-mouse-pointer"></i> PHP is an interpreted language: source → opcodes → Zend Engine execution.</p><div style="margin-top:12px; padding:12px; background:rgba(100,255,218,0.05); border-radius:5px; border:1px dashed rgba(100,255,218,0.2);"><p style="color:#64ffda;"><i class="fas fa-lightbulb"></i> <strong>Key Distinction:</strong></p><p style="font-size:0.8rem; color:#8892b0;">Unlike compiled languages (C, Go), PHP code is first compiled to Zend opcodes then executed by the Zend Engine.</p></div>`;
                this.clearScene(); this.createDefaultVisualization(); this.updateProgress(0); this.showStatus('Ready to interpret PHP code');
            }
            clearScene(){ this.objects.forEach(o=>this.scene.remove(o)); this.objects=[]; this.labels.forEach(l=>this.scene.remove(l)); this.labels=[]; }
            resetView(){ this.camera.position.set(0,30,50); this.controls.reset(); }
            zoom(f){ this.camera.position.multiplyScalar(f); this.controls.update(); }
            takeScreenshot(){ let link=document.createElement('a'); link.href=this.renderer.domElement.toDataURL('image/png'); link.download=`php_viz_${Date.now()}.png`; link.click(); }
            async download(type){
                if(!this.sessionId){ this.showStatus('No session','error'); return; }
                try{
                    let res = await fetch(`?api=download&session_id=${this.sessionId}&type=${type}`);
                    if(!res.ok) throw new Error('Download failed');
                    let blob = await res.blob(); let url=URL.createObjectURL(blob); let a=document.createElement('a');
                    a.href=url; a.download=type==='opcodes'?`opcodes_${this.sessionId}.phpc`:type==='ast'?`ast_${this.sessionId}.json`:`script_${this.sessionId}.php`;
                    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
                    this.showStatus(`${type} downloaded`,'success');
                }catch(e){ this.showStatus('Download failed','error'); }
            }
            loadExample(exampleId){
                if (phpExamples[exampleId]) {
                    document.getElementById('source-code').value = phpExamples[exampleId];
                    this.showStatus(`Loaded PHP example: ${exampleId}`, 'success');
                }
                document.getElementById('example-select').value = '';
            }
            onMouseMove(e){
                let rect = this.renderer.domElement.getBoundingClientRect();
                this.mouse.x = ((e.clientX-rect.left)/rect.width)*2-1;
                this.mouse.y = -((e.clientY-rect.top)/rect.height)*2+1;
                this.raycaster.setFromCamera(this.mouse,this.camera);
                let hits = this.raycaster.intersectObjects(this.objects);
                let tip = document.getElementById('tooltip');
                if(hits.length){
                    let data = hits[0].object.userData;
                    if(data){
                        tip.style.display='block'; tip.style.left=(e.clientX+10)+'px'; tip.style.top=(e.clientY+10)+'px';
                        tip.innerHTML = `<div style="color:#64ffda;">${data.name||'Node'}</div><div>Type: ${data.type}</div>${data.status?`<div>Status: ${data.status}</div>`:''}${data.error_count?`<div style="color:#ff6b6b;">${data.error_count} errors</div>`:''}`;
                    }
                } else tip.style.display='none';
            }
            handleResize(){ let c=document.getElementById('visualization-canvas'); this.camera.aspect=c.clientWidth/c.clientHeight; this.camera.updateProjectionMatrix(); this.renderer.setSize(c.clientWidth,c.clientHeight); this.isMobile=window.innerWidth<992; }
            animate(){ this.animationId=requestAnimationFrame(()=>this.animate()); if(this.autoRotate) this.scene.rotation.y+=0.001; this.controls.update(); this.renderer.render(this.scene,this.camera); }
        }
        document.addEventListener('DOMContentLoaded',()=>{ window.phpViz = new PhpInterpreterVisualizer3D(); });
    </script>
</body>
</html>