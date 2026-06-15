<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'brainfuck_errors.log');

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
            case 'compile': $result = handleBrainfuckCompile(); break;
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

function handleBrainfuckCompile() {
    $input = file_get_contents('php://input');
    if (empty($input)) return ['error' => 'No input data received'];
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['error' => 'Invalid JSON input: ' . json_last_error_msg()];
    $source_code = $data['source_code'] ?? '';
    if (empty($source_code)) return ['error' => 'No source code provided'];
    $session_id = uniqid('brainfuck_compile_', true);
    $tmp_dir = "tmp/{$session_id}";
    if (!mkdir($tmp_dir, 0777, true) && !is_dir($tmp_dir)) return ['error' => 'Failed to create temporary directory'];
    if (file_put_contents("{$tmp_dir}/program.bf", $source_code) === false) return ['error' => 'Failed to save source code'];
    $result = ['session_id' => $session_id, 'success' => true, 'stages' => [], 'outputs' => [], 'errors' => [], 'source_code' => $source_code];
    $lexicalResult = simulateBrainfuckTokens($source_code);
    $result['stages'][] = ['name' => 'Lexical Analysis', 'status' => $lexicalResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(50,200), 'tokens' => $lexicalResult['tokens'], 'errors' => $lexicalResult['errors']];
    $syntaxResult = simulateBrainfuckAST($source_code);
    $result['stages'][] = ['name' => 'Syntax Analysis', 'status' => $syntaxResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(100,300), 'ast' => $syntaxResult['ast'], 'errors' => $syntaxResult['errors']];
    $optimizationResult = simulateBrainfuckOptimization($source_code);
    $result['stages'][] = ['name' => 'Optimization', 'status' => $optimizationResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(80,250), 'optimizations' => $optimizationResult['optimizations'], 'errors' => $optimizationResult['errors']];
    $irResult = simulateBrainfuckIR($source_code);
    $result['stages'][] = ['name' => 'IR Generation', 'status' => $irResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(150,400), 'ir_code' => $irResult['ir'], 'errors' => $irResult['errors']];
    $executionResult = simulateBrainfuckExecution($source_code);
    $result['stages'][] = ['name' => 'Execution', 'status' => $executionResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(200,500), 'execution' => $executionResult['execution'], 'errors' => $executionResult['errors']];
    $all_errors = [];
    foreach ($result['stages'] as $stage) if (!empty($stage['errors'])) { $all_errors = array_merge($all_errors, $stage['errors']); $result['success'] = false; }
    $result['errors'] = $all_errors;
    $result['outputs'] = ['tokens' => $result['stages'][0]['tokens'], 'ast' => $result['stages'][1]['ast'], 'ir' => $result['stages'][3]['ir_code'], 'execution_trace' => $result['stages'][4]['execution']];
    file_put_contents("{$tmp_dir}/result.json", json_encode($result, JSON_PRETTY_PRINT));
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $language = 'brainfuck';
    $success = $result['success'];
    $errorsCount = count($result['errors']);
    $compilationTimeMs = array_sum(array_column($result['stages'], 'duration'));
    logCompilation($userId, $language, $session_id, $source_code, $success, $errorsCount, $compilationTimeMs);
    if ($userId) logActivity($userId, 'compile', "Compiled Brainfuck code, session: $session_id, errors: $errorsCount");
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
    $viz_data = generateBrainfuckVisualizationData($result, $stage, $view);
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
            $file = "{$tmp_dir}/program.bf";
            $filename = "program_{$session_id}.bf";
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
        case 'trace':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
            $result = json_decode(file_get_contents($result_file), true);
            $trace = $result['outputs']['execution_trace'] ?? [];
            $content = is_array($trace) ? json_encode($trace, JSON_PRETTY_PRINT) : $trace;
            $filename = "trace_{$session_id}.json";
            $content_type = 'application/json';
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

function simulateBrainfuckTokens($code) {
    $tokens = []; $errors = [];
    $valid_chars = ['>', '<', '+', '-', '.', ',', '[', ']'];
    $lines = explode("\n", $code);
    foreach ($lines as $lineNum => $line) {
        $characters = str_split($line);
        foreach ($characters as $charPos => $char) {
            if (trim($char) === '') continue;
            $token = ['type' => 'UNKNOWN', 'value' => $char, 'line' => $lineNum + 1, 'position' => $charPos + 1];
            if (in_array($char, $valid_chars)) {
                $token['type'] = 'INSTRUCTION';
                $descriptions = ['>' => 'move_right', '<' => 'move_left', '+' => 'increment', '-' => 'decrement', '.' => 'output', ',' => 'input', '[' => 'loop_start', ']' => 'loop_end'];
                $token['description'] = $descriptions[$char] ?? $char;
            } else {
                $errors[] = ['type' => 'lexical', 'message' => "Invalid Brainfuck character: '{$char}'", 'line' => $lineNum + 1, 'position' => $charPos + 1, 'severity' => 'error'];
            }
            $tokens[] = $token;
        }
    }
    return ['tokens' => $tokens, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateBrainfuckAST($code) {
    $errors = [];
    $stack = [];
    $lines = explode("\n", $code);
    foreach ($lines as $lineNum => $line) {
        $characters = str_split($line);
        foreach ($characters as $charPos => $char) {
            if ($char === '[') { $stack[] = ['line' => $lineNum + 1, 'position' => $charPos + 1]; }
            elseif ($char === ']') {
                if (empty($stack)) { $errors[] = ['type' => 'syntax', 'message' => "Unmatched closing bracket", 'line' => $lineNum + 1, 'position' => $charPos + 1, 'severity' => 'error']; }
                else { array_pop($stack); }
            }
        }
    }
    foreach ($stack as $bracket) { $errors[] = ['type' => 'syntax', 'message' => "Unmatched opening bracket", 'line' => $bracket['line'], 'position' => $bracket['position'], 'severity' => 'error']; }
    $ast = ['type' => 'Program', 'instructions' => [], 'loops' => []];
    $tokens = simulateBrainfuckTokens($code)['tokens'];
    $loop_depth = 0;
    $loop_stack = [];
    foreach ($tokens as $token) {
        if ($token['type'] !== 'INSTRUCTION') continue;
        if ($token['value'] === '[') {
            $loop_id = 'loop_' . count($loop_stack);
            $loop_node = ['type' => 'Loop', 'id' => $loop_id, 'depth' => $loop_depth, 'start_line' => $token['line'], 'start_position' => $token['position'], 'instructions' => []];
            $loop_stack[] = $loop_node;
            $loop_depth++;
        } elseif ($token['value'] === ']') {
            if (!empty($loop_stack)) {
                $current_loop = array_pop($loop_stack);
                $current_loop['end_line'] = $token['line'];
                $current_loop['end_position'] = $token['position'];
                if (empty($loop_stack)) { $ast['loops'][] = $current_loop; }
                else { $loop_stack[count($loop_stack)-1]['instructions'][] = $current_loop; }
                $loop_depth--;
            }
        } else {
            $instruction = ['type' => 'Instruction', 'value' => $token['value'], 'description' => $token['description'], 'line' => $token['line'], 'position' => $token['position']];
            if (empty($loop_stack)) { $ast['instructions'][] = $instruction; }
            else { $loop_stack[count($loop_stack)-1]['instructions'][] = $instruction; }
        }
    }
    return ['ast' => $ast, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateBrainfuckOptimization($code) {
    $optimizations = []; $errors = [];
    $cleaned = preg_replace('/[^><+\-.,\[\]]/', '', $code);
    $optimizations[] = 'Removed comments and whitespace';
    if (preg_match('/\+{3,}/', $cleaned)) $optimizations[] = 'Combine multiple increments';
    if (preg_match('/-{3,}/', $cleaned)) $optimizations[] = 'Combine multiple decrements';
    if (preg_match('/>{3,}/', $cleaned)) $optimizations[] = 'Combine multiple pointer moves';
    if (preg_match('/<{3,}/', $cleaned)) $optimizations[] = 'Combine multiple pointer moves';
    if (strpos($cleaned, '[-]') !== false) $optimizations[] = 'Clear cell optimization';
    if (strpos($cleaned, '[->+<]') !== false) $optimizations[] = 'Move pattern optimization';
    return ['optimizations' => $optimizations, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateBrainfuckIR($code) {
    $ir = []; $errors = [];
    $tokens = simulateBrainfuckTokens($code)['tokens'];
    $instruction_count = 0;
    foreach ($tokens as $token) {
        if ($token['type'] !== 'INSTRUCTION') continue;
        $instruction = ['id' => $instruction_count++, 'op' => $token['value'], 'desc' => $token['description'] ?? $token['value'], 'line' => $token['line'], 'pos' => $token['position']];
        $ir[] = $instruction;
    }
    return ['ir' => $ir, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateBrainfuckExecution($code) {
    $execution = ['tape' => [], 'pointer' => 0, 'output' => '', 'steps' => [], 'final_state' => []];
    $errors = [];
    $tape = array_fill(0, 10, 0);
    $pointer = 0;
    $output = '';
    $input_buffer = "Hello";
    $tokens = simulateBrainfuckTokens($code)['tokens'];
    $bf_instructions = array_filter($tokens, function($t) { return $t['type'] === 'INSTRUCTION'; });
    $bf_instructions = array_values($bf_instructions);
    $pc = 0;
    $step_count = 0;
    $max_steps = 1000;
    $bracket_pairs = [];
    $stack = [];
    for ($i = 0; $i < count($bf_instructions); $i++) {
        if ($bf_instructions[$i]['value'] === '[') { $stack[] = $i; }
        elseif ($bf_instructions[$i]['value'] === ']') {
            if (empty($stack)) { $errors[] = ['type' => 'execution', 'message' => 'Unmatched closing bracket', 'severity' => 'error']; break; }
            $start = array_pop($stack);
            $bracket_pairs[$start] = $i;
            $bracket_pairs[$i] = $start;
        }
    }
    while ($pc < count($bf_instructions) && $step_count < $max_steps) {
        $instruction = $bf_instructions[$pc];
        $step = ['step' => $step_count, 'pc' => $pc, 'instruction' => $instruction['value'], 'description' => $instruction['description'], 'pointer' => $pointer, 'tape_state' => array_slice($tape, 0, 10), 'output_so_far' => $output];
        switch ($instruction['value']) {
            case '>': $pointer = ($pointer + 1) % count($tape); break;
            case '<': $pointer = ($pointer - 1 + count($tape)) % count($tape); break;
            case '+': $tape[$pointer] = ($tape[$pointer] + 1) % 256; break;
            case '-': $tape[$pointer] = ($tape[$pointer] - 1 + 256) % 256; break;
            case '.': $output .= chr($tape[$pointer]); $step['output_char'] = chr($tape[$pointer]); break;
            case ',': if (!empty($input_buffer)) { $tape[$pointer] = ord($input_buffer[0]); $input_buffer = substr($input_buffer, 1); } else { $tape[$pointer] = 0; } break;
            case '[': if ($tape[$pointer] == 0) $pc = $bracket_pairs[$pc]; break;
            case ']': if ($tape[$pointer] != 0) $pc = $bracket_pairs[$pc]; break;
        }
        $execution['steps'][] = $step;
        $pc++;
        $step_count++;
    }
    if ($step_count >= $max_steps) $errors[] = ['type' => 'execution', 'message' => 'Execution timeout - possible infinite loop', 'severity' => 'error'];
    $execution['tape'] = array_slice($tape, 0, 10);
    $execution['pointer'] = $pointer;
    $execution['output'] = $output;
    $execution['final_state'] = ['tape' => array_slice($tape, 0, 10), 'pointer' => $pointer, 'output_length' => strlen($output)];
    return ['execution' => $execution, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function generateBrainfuckVisualizationData($result, $stage, $view) {
    $nodes = []; $edges = [];
    if ($view === 'pipeline') {
        $stages = [['id' => 'lexical', 'name' => 'Lexical Analysis', 'x' => -30, 'y' => 0, 'z' => 0], ['id' => 'syntax', 'name' => 'Syntax Analysis', 'x' => -18, 'y' => 0, 'z' => 0], ['id' => 'optimization', 'name' => 'Optimization', 'x' => -6, 'y' => 0, 'z' => 0], ['id' => 'ir', 'name' => 'IR Generation', 'x' => 6, 'y' => 0, 'z' => 0], ['id' => 'execution', 'name' => 'Execution', 'x' => 18, 'y' => 0, 'z' => 0]];
        foreach ($stages as $stage_info) {
            $stage_data = null;
            foreach ($result['stages'] as $s) if (strpos(strtolower($s['name']), strtolower($stage_info['id'])) !== false) { $stage_data = $s; break; }
            $has_errors = $stage_data && !empty($stage_data['errors']);
            $nodes[] = ['id' => $stage_info['id'], 'name' => $stage_info['name'], 'type' => 'stage', 'color' => $has_errors ? '#e74c3c' : '#2ecc71', 'position' => ['x' => $stage_info['x'], 'y' => $stage_info['y'], 'z' => $stage_info['z']], 'size' => 3, 'status' => $stage_data['status'] ?? 'pending', 'duration' => $stage_data['duration'] ?? 0, 'has_errors' => $has_errors, 'error_count' => $has_errors ? count($stage_data['errors']) : 0];
        }
        for ($i = 0; $i < count($stages) - 1; $i++) $edges[] = ['from' => $stages[$i]['id'], 'to' => $stages[$i+1]['id'], 'type' => 'pipeline', 'color' => '#3498db'];
    } elseif ($view === 'ast') {
        if (isset($result['outputs']['ast'])) { $ast = $result['outputs']['ast']; $node_id = 0; createBrainfuckASTNodes($ast, $nodes, $edges, $node_id, 0, 10, 0, -1); }
    } elseif ($view === 'tape') {
        if (isset($result['outputs']['execution_trace']['tape'])) {
            $tape = $result['outputs']['execution_trace']['tape']; $pointer = $result['outputs']['execution_trace']['pointer'] ?? 0;
            for ($i = 0; $i < count($tape); $i++) {
                $is_active = $i === $pointer; $value = $tape[$i]; $char = $value >= 32 && $value <= 126 ? chr($value) : '•';
                $nodes[] = ['id' => 'cell_' . $i, 'name' => "Cell $i: $value ($char)", 'type' => 'tape_cell', 'color' => $is_active ? '#e74c3c' : '#3498db', 'position' => ['x' => $i * 6 - 15, 'y' => 0, 'z' => 0], 'size' => 2, 'value' => $value, 'char' => $char, 'is_active' => $is_active, 'has_errors' => false, 'error_count' => 0];
                if ($i > 0) $edges[] = ['from' => 'cell_' . ($i-1), 'to' => 'cell_' . $i, 'type' => 'tape_connection', 'color' => '#95a5a6'];
            }
            $nodes[] = ['id' => 'pointer', 'name' => 'Pointer', 'type' => 'pointer', 'color' => '#e74c3c', 'position' => ['x' => $pointer * 6 - 15, 'y' => 5, 'z' => 0], 'size' => 1.5, 'has_errors' => false, 'error_count' => 0];
        }
    } elseif ($view === 'execution') {
        $exec_nodes = [['id' => 'start', 'name' => 'Start', 'x' => 0, 'y' => 20, 'z' => 0, 'type' => 'start'], ['id' => 'init', 'name' => 'Init Tape', 'x' => 0, 'y' => 10, 'z' => 0, 'type' => 'initialization'], ['id' => 'parse', 'name' => 'Parse', 'x' => -10, 'y' => 0, 'z' => 0, 'type' => 'process'], ['id' => 'execute', 'name' => 'Execute', 'x' => 0, 'y' => -10, 'z' => 0, 'type' => 'process'], ['id' => 'io', 'name' => 'I/O', 'x' => 10, 'y' => 0, 'z' => 0, 'type' => 'io'], ['id' => 'end', 'name' => 'End', 'x' => 0, 'y' => -20, 'z' => 0, 'type' => 'end']];
        foreach ($exec_nodes as $node) { $nodes[] = ['id' => $node['id'], 'name' => $node['name'], 'type' => $node['type'], 'color' => getExecutionNodeColor($node['type']), 'position' => ['x' => $node['x'], 'y' => $node['y'], 'z' => $node['z']], 'size' => 2.5, 'has_errors' => false, 'error_count' => 0]; }
        $exec_edges = [['from' => 'start', 'to' => 'init', 'type' => 'control_flow'], ['from' => 'init', 'to' => 'parse', 'type' => 'control_flow'], ['from' => 'parse', 'to' => 'execute', 'type' => 'control_flow'], ['from' => 'execute', 'to' => 'io', 'type' => 'data_flow'], ['from' => 'io', 'to' => 'execute', 'type' => 'data_flow'], ['from' => 'execute', 'to' => 'end', 'type' => 'control_flow']];
        foreach ($exec_edges as $edge) $edges[] = $edge;
    }
    return ['nodes' => $nodes, 'edges' => $edges, 'view' => $view, 'stage' => $stage, 'session_id' => $result['session_id'] ?? ''];
}

function getExecutionNodeColor($type) {
    $colors = ['start' => '#2ecc71', 'end' => '#e74c3c', 'process' => '#3498db', 'initialization' => '#f39c12', 'io' => '#9b59b6'];
    return $colors[$type] ?? '#95a5a6';
}

function createBrainfuckASTNodes($node, &$nodes, &$edges, &$node_id, $x, $y, $z, $parent_id) {
    $current_id = $node_id++;
    $node_type = $node['type'] ?? 'Unknown';
    $node_name = $node['type'] ?? 'Node';
    if ($node_type === 'Program') $node_name = 'Program';
    elseif ($node_type === 'Loop') $node_name = 'Loop (Depth ' . ($node['depth'] ?? 0) . ')';
    elseif ($node_type === 'Instruction') $node_name = $node['description'] ?? $node['value'] ?? 'Instruction';
    $nodes[] = ['id' => 'ast_node_' . $current_id, 'name' => $node_name, 'type' => $node_type, 'color' => getBrainfuckASTNodeColor($node_type), 'position' => ['x' => $x, 'y' => $y, 'z' => $z], 'size' => 2, 'has_errors' => false, 'error_count' => 0];
    if ($parent_id >= 0) $edges[] = ['from' => 'ast_node_' . $parent_id, 'to' => 'ast_node_' . $current_id, 'type' => 'parent_child', 'color' => '#2ecc71'];
    $child_index = 0;
    if (isset($node['instructions']) && is_array($node['instructions'])) foreach ($node['instructions'] as $child) if (is_array($child)) { $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5); createBrainfuckASTNodes($child, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++; }
    if (isset($node['loops']) && is_array($node['loops'])) foreach ($node['loops'] as $loop) { $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5); createBrainfuckASTNodes($loop, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++; }
    return $current_id;
}

function getBrainfuckASTNodeColor($type) {
    $colors = ['Program' => '#3498db', 'Loop' => '#e74c3c', 'Instruction' => '#2ecc71', 'move_right' => '#f39c12', 'move_left' => '#f39c12', 'increment' => '#9b59b6', 'decrement' => '#9b59b6', 'output' => '#1abc9c', 'input' => '#1abc9c', 'loop_start' => '#e74c3c', 'loop_end' => '#e74c3c'];
    return $colors[$type] ?? '#95a5a6';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>3D Brainfuck Compiler Visualizer - Final Project</title>
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
            flex: 1;
            padding: 10px;
            overflow: hidden;
            height: calc(100vh - 140px);
        }
        
        .main-content {
            display: flex;
            flex: 1;
            gap: 10px;
            overflow: hidden;
            height: 100%;
        }
        
        @media (max-width: 992px) {
            .main-content {
                flex-direction: column;
            }
        }
        
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
        
        .controls-panel {
            width: 400px;
            min-width: 400px;
            display: flex;
            flex-direction: column;
        }
        
        @media (max-width: 992px) {
            .controls-panel {
                width: 100%;
                min-width: 100%;
                height: 400px;
            }
        }
        
        .visualization-panel {
            flex: 1;
            min-width: 0;
        }
        
        .output-panel {
            width: 450px;
            min-width: 450px;
            display: flex;
            flex-direction: column;
        }
        
        @media (max-width: 992px) {
            .output-panel {
                width: 100%;
                min-width: 100%;
                height: 500px;
            }
        }
        
        .panel-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(100, 255, 218, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
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
        
        .controls-scroll-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 5px;
            min-height: 0;
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
        
        .visualization-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            min-height: 0;
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
        
        .output-scroll-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }
        
        .status-bar {
            padding: 12px;
            background: rgba(10, 25, 47, 0.8);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
            margin-top: 15px;
            flex-shrink: 0;
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
            display: flex;
            flex-direction: column;
            margin-top: 15px;
            flex-shrink: 0;
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
        
        textarea:focus {
            outline: none;
            border-color: #64ffda;
        }
        
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
        
        .output-display.active {
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
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(10, 25, 47, 0.5);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.3);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 255, 218, 0.5);
        }
        
        @media (max-width: 992px) {
            .container {
                height: auto;
                min-height: calc(100vh - 140px);
            }
            
            .main-content {
                flex-direction: column;
                height: auto;
            }
            
            .controls-panel, .output-panel {
                height: auto;
                max-height: 500px;
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
            
            .visualization-panel {
                height: 400px;
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
            }
            
            .code-editor-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            #example-select {
                width: 100%;
                margin-top: 5px;
            }
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
        
        textarea::-webkit-scrollbar {
            width: 8px;
        }
        
        textarea::-webkit-scrollbar-track {
            background: rgba(10, 25, 47, 0.5);
        }
        
        textarea::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.3);
            border-radius: 4px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .controls-panel {
            height: 100%;
        }
        
        #example-select {
            padding: 6px 10px;
            background: rgba(10, 25, 47, 0.8);
            color: #e6f1ff;
            border-radius: 5px;
            border: 1px solid rgba(100, 255, 218, 0.2);
            font-size: 0.85rem;
        }
        
        .controls-scroll-container > * {
            flex-shrink: 0;
        }
        
        .brainfuck-key {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            padding: 10px;
            background: rgba(10, 25, 47, 0.5);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .brainfuck-key-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            color: #8892b0;
        }
        
        .brainfuck-key-item .key {
            background: rgba(100, 255, 218, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'JetBrains Mono', monospace;
            color: #64ffda;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1><i class="fas fa-brain"></i> 3D Brainfuck Compiler Visualizer</h1>
            <div class="subtitle">Interactive Visualization of Brainfuck Compilation & Execution</div>
        </div>
        <div class="course-info">
            <span class="university">COMPUTER SCIENCE FINAL PROJECT - BRAINFUCK VERSION</span>
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
                    <h2>Brainfuck Compilation Controls</h2>
                </div>
                
                <div class="controls-scroll-container">
                    <div class="control-group">
                        <label for="view-mode"><i class="fas fa-eye"></i> View Mode:</label>
                        <select id="view-mode">
                            <option value="pipeline">Pipeline View</option>
                            <option value="ast">AST View</option>
                            <option value="tape">Tape Memory</option>
                            <option value="execution">Execution Flow</option>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label for="stage-select"><i class="fas fa-code-branch"></i> Pipeline Stage:</label>
                        <select id="stage-select">
                            <option value="all">All Stages</option>
                            <option value="lexical">Lexical Analysis</option>
                            <option value="syntax">Syntax Analysis</option>
                            <option value="optimization">Optimization</option>
                            <option value="ir">IR Generation</option>
                            <option value="execution">Execution</option>
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
                    
                    <div class="brainfuck-key">
                        <div class="brainfuck-key-item"><span class="key">></span> Move right</div>
                        <div class="brainfuck-key-item"><span class="key"><</span> Move left</div>
                        <div class="brainfuck-key-item"><span class="key">+</span> Increment</div>
                        <div class="brainfuck-key-item"><span class="key">-</span> Decrement</div>
                        <div class="brainfuck-key-item"><span class="key">.</span> Output</div>
                        <div class="brainfuck-key-item"><span class="key">,</span> Input</div>
                        <div class="brainfuck-key-item"><span class="key">[</span> Loop start</div>
                        <div class="brainfuck-key-item"><span class="key">]</span> Loop end</div>
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
                        <h2>Brainfuck Source Code Editor</h2>
                    </div>
                    
                    <div class="code-editor">
                        <div class="code-editor-header">
                            <h3><i class="fas fa-brain"></i> Brainfuck Program</h3>
                            <select id="example-select">
                                <option value="">Load Example...</option>
                                <option value="hello">Hello World</option>
                                <option value="addition">Addition</option>
                                <option value="loop">Simple Loop</option>
                                <option value="cat">Echo (Cat)</option>
                            </select>
                        </div>
                        <textarea id="source-code" spellcheck="false">++++++++++[>+++++++>++++++++++>+++>+<<<<-]
>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.</textarea>
                    </div>
                    
                    <div class="button-group" style="margin-top: 15px;">
                        <button id="compile-btn" class="btn">
                            <i class="fas fa-play"></i> Compile & Visualize
                        </button>
                        <button id="reset-btn" class="btn danger">
                            <i class="fas fa-trash"></i> Reset
                        </button>
                    </div>
                    
                    <div class="status-bar">
                        <div id="status-message">
                            <i class="fas fa-info-circle"></i> Ready to compile Brainfuck code
                        </div>
                        <div class="progress-bar">
                            <div id="progress-bar" class="progress-fill"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Center Panel - Visualization -->
            <div class="panel visualization-panel">
                <div class="panel-header">
                    <i class="fas fa-cube"></i>
                    <h2>3D Visualization</h2>
                </div>
                <div class="visualization-container">
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
            </div>
            
            <!-- Right Panel - Output -->
            <div class="panel output-panel" id="output-panel">
                <button class="mobile-menu-toggle" id="output-toggle">
                    <i class="fas fa-chevron-down"></i>
                    <span>Output Panel</span>
                </button>
                
                <div class="panel-header">
                    <i class="fas fa-terminal"></i>
                    <h2>Brainfuck Compilation Output</h2>
                </div>
                
                <div class="output-scroll-container">
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
                        <button class="output-tab" data-output="execution">
                            <i class="fas fa-play-circle"></i> <span class="tab-text">Execution</span>
                        </button>
                        <button class="output-tab" data-output="errors" id="errors-tab" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> <span class="tab-text">Errors</span>
                        </button>
                    </div>
                    
                    <!-- Output display area -->
                    <div id="tokens-output" class="output-display active"></div>
                    <div id="ast-output" class="output-display"></div>
                    <div id="ir-output" class="output-display"></div>
                    <div id="execution-output" class="output-display"></div>
                    <div id="errors-output" class="output-display"></div>
                    
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
                    
                    <div class="export-buttons">
                        <button id="download-ast" class="btn secondary">
                            <i class="fas fa-download"></i> <span class="btn-text">AST (JSON)</span>
                        </button>
                        <button id="download-trace" class="btn secondary">
                            <i class="fas fa-download"></i> <span class="btn-text">Execution Trace</span>
                        </button>
                        <button id="download-source" class="btn secondary">
                            <i class="fas fa-download"></i> <span class="btn-text">Source Code</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <div class="footer-content">
            <div class="course-info">
                <span>Final Year Project - Computer Science Department | Brainfuck Compiler & Tape Memory 3D Visualization System</span>
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
            <a href="https://github.com/Agabaofficial/brainfuck-compiler-3d-visualizer" target="_blank" class="github-link">
                <i class="fab fa-github"></i> View on GitHub
            </a>
        </div>
    </div>
    
    <div id="tooltip" class="tooltip"></div>

    <script>
        class BrainfuckCompilerVisualizer3D {
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
                document.getElementById('download-trace').addEventListener('click', () => this.download('trace'));
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
                if (!sourceCode.trim()) { this.showStatus('Please enter Brainfuck source code', 'error'); return; }
                this.showStatus('Compiling Brainfuck code...', 'info');
                this.updateProgress(10);
                try {
                    const response = await fetch('?api=compile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ source_code: sourceCode }) });
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const text = await response.text();
                    let data;
                    try { data = JSON.parse(text); } catch(e) { throw new Error('Invalid JSON response'); }
                    if (data.error) throw new Error(data.error);
                    this.sessionId = data.session_id;
                    this.totalErrors = data.errors?.length || 0;
                    this.updateProgress(50);
                    this.updateOutputs(data);
                    if (this.totalErrors > 0) { this.showOutput('errors'); document.getElementById('errors-tab').style.display = 'flex'; } else { this.showOutput('tokens'); }
                    await this.visualize();
                    this.updateProgress(100);
                    this.showStatus(data.success ? 'Brainfuck compilation successful!' : 'Compilation completed with warnings', data.success ? 'success' : 'warning');
                } catch (error) { this.showStatus('Error: ' + error.message, 'error'); this.updateProgress(0); console.error(error); }
            }
            
            updateOutputs(data) {
                if (data.outputs && data.outputs.tokens) { const tokensText = data.outputs.tokens.map(t => `Line ${t.line}, Pos ${t.position}: ${t.type.padEnd(12)} = "${t.value}" (${t.description || ''})`).join('\n'); document.getElementById('tokens-output').textContent = tokensText; }
                if (data.outputs && data.outputs.ast) { document.getElementById('ast-output').textContent = JSON.stringify(data.outputs.ast, null, 2); }
                if (data.outputs && data.outputs.ir) { const irText = data.outputs.ir.map((instr, idx) => `${idx.toString().padStart(3)}: ${instr.op} (${instr.desc}) [Line ${instr.line}, Pos ${instr.pos}]`).join('\n'); document.getElementById('ir-output').textContent = irText; }
                if (data.outputs && data.outputs.execution_trace) {
                    const exec = data.outputs.execution_trace;
                    let execText = `=== BRAINFUCK EXECUTION TRACE ===\n\nFinal Output: "${exec.output}"\nPointer Position: ${exec.pointer}\nTotal Steps: ${exec.steps?.length || 0}\n\n`;
                    if (exec.steps && exec.steps.length > 0) {
                        execText += `Step-by-step execution:\n`;
                        exec.steps.slice(0, 20).forEach(step => { execText += `Step ${step.step}: ${step.instruction} (${step.description})\n  Pointer: ${step.pointer}, Tape: [${step.tape_state.join(', ')}]\n`; if (step.output_char) execText += `  Output: '${step.output_char}'\n`; execText += '\n'; });
                        if (exec.steps.length > 20) execText += `... and ${exec.steps.length - 20} more steps\n`;
                    }
                    execText += `\nFinal Tape State: [${exec.tape?.join(', ') || ''}]`;
                    document.getElementById('execution-output').textContent = execText;
                }
                if (data.errors && data.errors.length > 0) {
                    const errorsText = data.errors.map((err, idx) => `${idx+1}. ${err.type || 'Error'}:\n   ${err.message}\n   Line: ${err.line || 'N/A'}, Position: ${err.position || 'N/A'}\n`).join('\n');
                    document.getElementById('errors-output').textContent = errorsText;
                    let errorHtml = '';
                    data.errors.forEach((error) => { const errorClass = error.severity === 'warning' ? 'error-warning' : ''; const icon = error.severity === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'; errorHtml += `<div class="error-item ${errorClass}"><div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;"><i class="fas ${icon}"></i> <strong>${error.type || 'Error'}:</strong> ${error.message}</div>${error.line ? `<div style="font-size:0.85rem; color:#ffd166;">Line ${error.line}, Position ${error.position}</div>` : ''}</div>`; });
                    document.getElementById('error-list').innerHTML = errorHtml;
                    document.getElementById('error-section').style.display = 'block';
                } else { document.getElementById('error-section').style.display = 'none'; }
                if (data.stages && data.stages.length > 0) {
                    let stageHtml = '<h3 style="color:#64ffda; margin-bottom:12px; font-size:1rem;">Brainfuck Compilation Pipeline</h3>';
                    data.stages.forEach(stage => { const statusClass = `status-${stage.status || 'pending'}`; const icon = stage.status === 'completed' ? 'fa-check-circle' : stage.status === 'failed' ? 'fa-times-circle' : 'fa-clock'; stageHtml += `<div style="margin-bottom:10px; padding:12px; background:rgba(100,255,218,0.05); border-radius:6px; border:1px solid rgba(100,255,218,0.1);"><div style="display:flex; justify-content:space-between; align-items:center;"><div style="display:flex; align-items:center; gap:8px;"><i class="fas ${icon}" style="color:${stage.status === 'completed' ? '#64ffda' : stage.status === 'failed' ? '#ff6b6b' : '#8892b0'}"></i><strong style="font-size:0.9rem;">${stage.name}</strong></div><span class="stage-status ${statusClass}" style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:0.7rem; font-weight:bold; margin-left:8px; ${stage.status === 'completed' ? 'background:rgba(100,255,218,0.2); color:#64ffda' : stage.status === 'failed' ? 'background:rgba(255,107,107,0.2); color:#ff6b6b' : 'background:rgba(136,146,176,0.2); color:#8892b0'}">${stage.status || 'pending'}</span></div><div style="font-size:0.8rem; margin-top:6px; color:#8892b0;"><i class="far fa-clock"></i> Duration: ${stage.duration}ms${stage.errors && stage.errors.length > 0 ? `<br><i class="fas fa-exclamation-circle"></i> Errors: ${stage.errors.length}` : ''}</div></div>`; });
                    document.getElementById('stage-info').innerHTML = stageHtml;
                }
            }
            
            showOutput(outputType) {
                document.querySelectorAll('.output-tab').forEach(tab => { tab.classList.remove('active'); if (tab.dataset.output === outputType) tab.classList.add('active'); });
                document.querySelectorAll('.output-display').forEach(content => { content.classList.remove('active'); if (content.id === `${outputType}-output`) content.classList.add('active'); });
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
                const colors = [0x64ffda, 0x00d9a6, 0xff6b6b, 0xffa502, 0x9b59b6];
                for (let i = 0; i < 5; i++) { const geometry = new THREE.SphereGeometry(3, 32, 32); const material = new THREE.MeshPhongMaterial({ color: colors[i], emissive: colors[i], emissiveIntensity: 0.2, transparent: true, opacity: 0.9 }); const sphere = new THREE.Mesh(geometry, material); sphere.position.set(i * 12 - 24, 0, 0); sphere.castShadow = true; this.scene.add(sphere); this.objects.push(sphere); }
            }
            
            createNode(node) {
                let geometry, material; const color = new THREE.Color(node.color || '#64ffda');
                if (node.type === 'tape_cell') geometry = new THREE.BoxGeometry(4,4,4);
                else if (node.type === 'pointer') geometry = new THREE.ConeGeometry(node.size||2,6,16);
                else if (node.type === 'start' || node.type === 'end') geometry = new THREE.CylinderGeometry(node.size||2.5,node.size||2.5,5,16);
                else geometry = new THREE.SphereGeometry(node.size||2.5,32,32);
                material = new THREE.MeshPhongMaterial({ color: color, emissive: color, emissiveIntensity: 0.2, transparent: true, opacity: 0.9, shininess: 100 });
                const mesh = new THREE.Mesh(geometry, material);
                mesh.position.set(node.position?.x||0, node.position?.y||0, node.position?.z||0);
                mesh.castShadow = true; mesh.receiveShadow = true; mesh.userData = node;
                this.scene.add(mesh); this.objects.push(mesh);
                if (node.has_errors && this.showErrors) this.addErrorIndicator(mesh);
                if (node.type === 'start' || node.type === 'end' || node.is_active) this.addGlowEffect(mesh);
                if (node.type === 'tape_cell' && node.value !== undefined) this.addValueDisplay(mesh, node.value, node.char);
            }
            
            addErrorIndicator(mesh) { const node = mesh.userData; const geometry = new THREE.SphereGeometry((node.size||2.5)*1.3,16,16); const material = new THREE.MeshBasicMaterial({ color:0xff6b6b, transparent:true, opacity:0.3, side:THREE.DoubleSide }); const indicator = new THREE.Mesh(geometry,material); indicator.position.copy(mesh.position); this.scene.add(indicator); this.objects.push(indicator); }
            addGlowEffect(mesh) { const geometry = new THREE.SphereGeometry((mesh.userData.size||2.5)*1.5,16,16); const material = new THREE.MeshBasicMaterial({ color:0x64ffda, transparent:true, opacity:0.2, side:THREE.DoubleSide }); const glow = new THREE.Mesh(geometry,material); glow.position.copy(mesh.position); this.scene.add(glow); this.objects.push(glow); }
            addValueDisplay(mesh, value, char) { const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d'); canvas.width=64; canvas.height=32; ctx.fillStyle='rgba(10,25,47,0.8)'; ctx.fillRect(0,0,canvas.width,canvas.height); ctx.font='bold 12px Arial'; ctx.fillStyle='#64ffda'; ctx.textAlign='center'; ctx.fillText(value.toString(), canvas.width/2,20); ctx.font='10px Arial'; ctx.fillStyle='#8892b0'; ctx.fillText(char, canvas.width/2,30); const texture = new THREE.CanvasTexture(canvas); const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map:texture, transparent:true })); sprite.position.copy(mesh.position); sprite.position.y+=3; sprite.scale.set(3,1.5,1); this.scene.add(sprite); this.objects.push(sprite); }
            
            createEdge(edge, nodes) {
                const fromNode = this.findObjectById(edge.from); const toNode = this.findObjectById(edge.to);
                if (!fromNode || !toNode) return;
                const curve = new THREE.CatmullRomCurve3([fromNode.position.clone(), new THREE.Vector3((fromNode.position.x+toNode.position.x)/2, (fromNode.position.y+toNode.position.y)/2+8, (fromNode.position.z+toNode.position.z)/2), toNode.position.clone()]);
                const geometry = new THREE.TubeGeometry(curve,20,0.2,8,false);
                const material = new THREE.MeshBasicMaterial({ color: this.getEdgeColor(edge.type), transparent:true, opacity:0.7 });
                const tube = new THREE.Mesh(geometry,material);
                this.scene.add(tube); this.objects.push(tube);
            }
            
            getEdgeColor(type) { const colors = { 'pipeline':0x64ffda, 'parent_child':0x00d9a6, 'control_flow':0xffa502, 'data_flow':0x9b59b6, 'tape_connection':0x95a5a6 }; return colors[type]||0x8892b0; }
            
            addLabels(nodes) {
                this.labels.forEach(label => this.scene.remove(label)); this.labels = [];
                nodes.forEach(node => {
                    if (node.type === 'tape_cell') return;
                    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d'); canvas.width=250; canvas.height=100;
                    const grad = ctx.createLinearGradient(0,0,canvas.width,0); grad.addColorStop(0,'rgba(10,25,47,0.9)'); grad.addColorStop(1,'rgba(17,34,64,0.9)'); ctx.fillStyle=grad; ctx.fillRect(0,0,canvas.width,canvas.height);
                    ctx.strokeStyle=node.color||'#64ffda'; ctx.lineWidth=2; ctx.strokeRect(1,1,canvas.width-2,canvas.height-2);
                    ctx.font='bold 14px "Inter", sans-serif'; ctx.fillStyle='#64ffda'; ctx.textAlign='center'; ctx.fillText(this.truncateText(node.name,20), canvas.width/2,25);
                    ctx.font='12px "Inter", sans-serif'; ctx.fillStyle='#8892b0'; ctx.fillText(`Type: ${node.type}`, canvas.width/2,45);
                    if (node.value!==undefined) { ctx.font='11px "Inter", sans-serif'; ctx.fillStyle='#00d9a6'; ctx.fillText(`Value: ${node.value}`, canvas.width/2,65); }
                    if (node.error_count>0) { ctx.fillStyle='#ff6b6b'; ctx.font='11px "Inter", sans-serif'; ctx.fillText(`${node.error_count} error(s)`, canvas.width/2,85); } else if (node.duration) { ctx.fillStyle='#8892b0'; ctx.font='11px "Inter", sans-serif'; ctx.fillText(`Duration: ${node.duration}ms`, canvas.width/2,85); }
                    const texture = new THREE.CanvasTexture(canvas); const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map:texture, transparent:true })); sprite.position.set(node.position?.x||0, (node.position?.y||0)+(node.height||node.size||2.5)+2, node.position?.z||0); sprite.scale.set(10,4,1); this.scene.add(sprite); this.labels.push(sprite);
                });
            }
            
            truncateText(text, maxLength) { return text.length<=maxLength?text:text.substring(0,maxLength)+'...'; }
            toggleLabels() { this.labels.forEach(label=>label.visible=this.showLabels); }
            findObjectById(id) { return this.objects.find(obj=>obj.userData?.id===id); }
            showStatus(message, type='info') { const statusEl = document.getElementById('status-message'); const icon = type==='error'?'fa-exclamation-circle':type==='warning'?'fa-exclamation-triangle':type==='success'?'fa-check-circle':'fa-info-circle'; statusEl.innerHTML=`<i class="fas ${icon}"></i> ${message}`; const progressBar=document.getElementById('progress-bar'); progressBar.className='progress-fill'; if(type==='error'){ statusEl.style.color='#ff6b6b'; progressBar.classList.add('error-indicator'); } else if(type==='warning') statusEl.style.color='#ffa502'; else if(type==='success') statusEl.style.color='#00d9a6'; else statusEl.style.color='#64ffda'; }
            updateProgress(percent) { document.getElementById('progress-bar').style.width=percent+'%'; }
            reset() { this.sessionId=null; this.totalErrors=0; document.getElementById('source-code').value=`++++++++++[>+++++++>++++++++++>+++>+<<<<-]\n>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.`; document.getElementById('tokens-output').textContent=''; document.getElementById('ast-output').textContent=''; document.getElementById('ir-output').textContent=''; document.getElementById('execution-output').textContent=''; document.getElementById('errors-output').textContent=''; this.showOutput('tokens'); document.getElementById('errors-tab').style.display='none'; document.getElementById('error-section').style.display='none'; document.getElementById('stage-info').innerHTML=`<p><i class="fas fa-mouse-pointer"></i> Select a compilation stage or node to view details</p><div style="margin-top:12px; padding:12px; background:rgba(100,255,218,0.05); border-radius:5px; border:1px dashed rgba(100,255,218,0.2);"><p style="color:#64ffda; margin-bottom:6px; font-size:0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Tip:</strong></p><p style="font-size:0.8rem; color:#8892b0;">Hover over nodes in the 3D visualization to see detailed information. Click and drag to rotate the view.</p></div>`; this.clearScene(); this.createDefaultVisualization(); this.updateProgress(0); this.showStatus('Ready to compile Brainfuck code'); }
            clearScene() { this.objects.forEach(obj=>this.scene.remove(obj)); this.objects=[]; this.labels.forEach(label=>this.scene.remove(label)); this.labels=[]; }
            resetView() { this.camera.position.set(0,30,50); this.controls.reset(); }
            zoom(factor) { this.camera.position.multiplyScalar(factor); this.controls.update(); }
            takeScreenshot() { this.renderer.render(this.scene,this.camera); const link=document.createElement('a'); link.href=this.renderer.domElement.toDataURL('image/png'); link.download=`brainfuck_compiler_visualization_${Date.now()}.png`; link.click(); }
            async download(type) { if(!this.sessionId){ this.showStatus('No compilation session found','error'); return; } this.showStatus(`Downloading ${type}...`,'info'); try{ const response=await fetch(`?api=download&session_id=${this.sessionId}&type=${type}`); if(!response.ok) throw new Error('Download failed'); const blob=await response.blob(); const url=window.URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download=type==='trace'?`execution_trace_${this.sessionId}.json`:type==='ast'?`ast_${this.sessionId}.json`:`program_${this.sessionId}.bf`; document.body.appendChild(a); a.click(); document.body.removeChild(a); window.URL.revokeObjectURL(url); this.showStatus(`${type} downloaded successfully`,'success'); } catch(error){ this.showStatus('Download failed: '+error.message,'error'); } }
            loadExample(exampleId) { const examples={ hello:`++++++++++[>+++++++>++++++++++>+++>+<<<<-]\n>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.`, addition:`+++       Cell 0 = 3\n> ++++    Cell 1 = 4\n[<+>-]    Add cell 1 to cell 0\n< .       Output cell 0`, loop:`+++++ +++++\n[\n > +++++ ++\n > +++++ +++++\n > +++\n > +\n <<<< -\n]\n> ++ .\n> + .\n+++++++ .\n+++ .\n> ++ .\n<< +++++ +++++ +++++ .\n> .\n+++ .\n----- - .\n----- --- .\n> + .\n> .`, cat:`,[.,]` }; if(examples[exampleId]){ document.getElementById('source-code').value=examples[exampleId]; this.showStatus(`Loaded Brainfuck example: ${exampleId}`,'success'); } document.getElementById('example-select').value=''; }
            onMouseMove(event) { const rect=this.renderer.domElement.getBoundingClientRect(); this.mouse.x=((event.clientX-rect.left)/rect.width)*2-1; this.mouse.y=-((event.clientY-rect.top)/rect.height)*2+1; this.raycaster.setFromCamera(this.mouse,this.camera); const intersects=this.raycaster.intersectObjects(this.objects); const tooltip=document.getElementById('tooltip'); if(intersects.length>0){ const data=intersects[0].object.userData; if(data){ tooltip.style.display='block'; tooltip.style.left=(event.clientX+10)+'px'; tooltip.style.top=(event.clientY+10)+'px'; let html=`<div style="color:#64ffda; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">${this.truncateText(data.name,25)}</div>`; html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Type:</strong> ${data.type}</div>`; if(data.status) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Status:</strong> ${data.status}</div>`; if(data.value!==undefined) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Value:</strong> ${data.value}</div>`; if(data.char) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Char:</strong> '${data.char}'</div>`; if(data.is_active) html+=`<div style="color:#ff6b6b; margin-top:6px; font-size:0.8rem;"><i class="fas fa-bullseye"></i> Active Cell</div>`; if(data.error_count>0) html+=`<div style="color:#ff6b6b; margin-top:6px; font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> ${data.error_count} error(s)</div>`; tooltip.innerHTML=html; } } else { tooltip.style.display='none'; } }
            handleResize() { const container=document.getElementById('visualization-canvas'); this.camera.aspect=container.clientWidth/container.clientHeight; this.camera.updateProjectionMatrix(); this.renderer.setSize(container.clientWidth,container.clientHeight); this.isMobile=window.innerWidth<992; }
            animate() { this.animationId=requestAnimationFrame(()=>this.animate()); if(this.autoRotate) this.scene.rotation.y+=0.001; this.controls.update(); this.renderer.render(this.scene,this.camera); }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            window.brainfuckVisualizer = new BrainfuckCompilerVisualizer3D();
            if (window.innerWidth < 768) {
                document.querySelectorAll('.tab-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
                document.querySelectorAll('.btn-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
            }
            if (window.innerWidth < 768) { const textarea = document.getElementById('source-code'); textarea.style.minHeight = '150px'; }
        });
    </script>
</body>
</html>