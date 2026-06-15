<?php
// python.php - Python Bytecode & Interpreter Visualizer (Standalone)

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'python_errors.log');

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
            case 'compile': $result = handlePythonCompile(); break;
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

function handlePythonCompile() {
    $input = file_get_contents('php://input');
    if (empty($input)) return ['error' => 'No input data received'];
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['error' => 'Invalid JSON input: ' . json_last_error_msg()];
    $source_code = $data['source_code'] ?? '';
    if (empty($source_code)) return ['error' => 'No source code provided'];
    $session_id = uniqid('python_compile_', true);
    $tmp_dir = "tmp/{$session_id}";
    if (!mkdir($tmp_dir, 0777, true) && !is_dir($tmp_dir)) return ['error' => 'Failed to create temporary directory'];
    if (file_put_contents("{$tmp_dir}/script.py", $source_code) === false) return ['error' => 'Failed to save source code'];
    
    $result = ['session_id' => $session_id, 'success' => true, 'stages' => [], 'outputs' => [], 'errors' => [], 'source_code' => $source_code];
    
    $lexicalResult = simulatePythonTokens($source_code);
    $result['stages'][] = ['name' => 'Lexical Analysis', 'status' => $lexicalResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(40,150), 'tokens' => $lexicalResult['tokens'], 'errors' => $lexicalResult['errors']];
    
    $syntaxResult = simulatePythonAST($source_code);
    $result['stages'][] = ['name' => 'Syntax Analysis', 'status' => $syntaxResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(80,250), 'ast' => $syntaxResult['ast'], 'errors' => $syntaxResult['errors']];
    
    $semanticResult = simulatePythonSymbolTable($source_code);
    $result['stages'][] = ['name' => 'Semantic Analysis', 'status' => $semanticResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(70,220), 'symbol_table' => $semanticResult['symbol_table'], 'errors' => $semanticResult['errors']];
    
    $bytecodeResult = simulatePythonBytecode($source_code);
    $result['stages'][] = ['name' => 'Bytecode Compilation', 'status' => $bytecodeResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(120,350), 'bytecode' => $bytecodeResult['bytecode'], 'errors' => $bytecodeResult['errors']];
    
    $executionResult = simulatePythonExecution($source_code);
    $result['stages'][] = ['name' => 'Interpretation (PVM)', 'status' => $executionResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(180,450), 'execution' => $executionResult['execution'], 'errors' => $executionResult['errors']];
    
    $all_errors = [];
    foreach ($result['stages'] as $stage) if (!empty($stage['errors'])) { $all_errors = array_merge($all_errors, $stage['errors']); $result['success'] = false; }
    $result['errors'] = $all_errors;
    $result['outputs'] = [
        'tokens' => $result['stages'][0]['tokens'],
        'ast' => $result['stages'][1]['ast'],
        'bytecode' => $result['stages'][3]['bytecode'],
        'execution_output' => $result['stages'][4]['execution']
    ];
    file_put_contents("{$tmp_dir}/result.json", json_encode($result, JSON_PRETTY_PRINT));
    
    // Logging
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $language = 'python';
    $success = $result['success'];
    $errorsCount = count($result['errors']);
    $compilationTimeMs = array_sum(array_column($result['stages'], 'duration'));
    logCompilation($userId, $language, $session_id, $source_code, $success, $errorsCount, $compilationTimeMs);
    if ($userId) logActivity($userId, 'compile', "Compiled/interpreted Python code, session: $session_id, errors: $errorsCount");
    
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
    $viz_data = generatePythonVisualizationData($result, $stage, $view);
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
            $file = "{$tmp_dir}/script.py";
            $filename = "script_{$session_id}.py";
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
        case 'bytecode':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
            $result = json_decode(file_get_contents($result_file), true);
            $bytecode = $result['outputs']['bytecode'] ?? [];
            $content = is_array($bytecode) ? implode("\n", $bytecode) : $bytecode;
            $filename = "bytecode_{$session_id}.pyc";
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

// ---------- Python simulation functions ----------
function simulatePythonTokens($code) {
    $tokens = []; $errors = [];
    $code = preg_replace('/#.*$/m', '', $code);
    $lines = explode("\n", $code);
    $keywords = ['def', 'if', 'elif', 'else', 'for', 'while', 'break', 'continue', 'return', 'import', 'from', 'as', 'class', 'try', 'except', 'finally', 'raise', 'with', 'lambda', 'and', 'or', 'not', 'in', 'is', 'None', 'True', 'False', 'print', 'len', 'range', 'int', 'str', 'float', 'list', 'dict', 'set', 'tuple'];
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if ($line === '') continue;
        $words = preg_split('/(\s+|(?<=[(){}\[\],:;=+\-*/%<>!&|])|(?=[(){}\[\],:;=+\-*/%<>!&|]))/', $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') continue;
            $type = 'IDENTIFIER';
            if (in_array($word, $keywords)) $type = 'KEYWORD';
            elseif (is_numeric($word)) $type = 'LITERAL';
            elseif (preg_match('/^".*"$|^\'.*\'$/', $word)) $type = 'STRING_LITERAL';
            elseif (preg_match('/^[+\-*/%<>!&|]=?|==|<=|>=|!=/', $word)) $type = 'OPERATOR';
            elseif (preg_match('/^[(){}\[\],:;]$/', $word)) $type = 'PUNCTUATOR';
            $tokens[] = ['type' => $type, 'value' => $word, 'line' => $lineNum+1];
        }
    }
    return ['tokens' => $tokens, 'errors' => $errors, 'has_errors' => false];
}

function simulatePythonAST($code) {
    $errors = [];
    $lines = explode("\n", $code);
    foreach ($lines as $lineNum => $line) {
        if (trim($line) === '') continue;
        $leading = strlen($line) - strlen(ltrim($line));
        if ($leading % 4 != 0) $errors[] = ['type' => 'syntax', 'message' => 'Inconsistent indentation', 'line' => $lineNum+1, 'severity' => 'error'];
    }
    $ast = ['type' => 'Module', 'body' => []];
    if (strpos($code, 'def ') !== false) $ast['body'][] = ['type' => 'FunctionDef', 'name' => 'example', 'body' => []];
    if (strpos($code, 'print(') !== false) $ast['body'][] = ['type' => 'Expr', 'value' => ['type' => 'Call', 'func' => ['type' => 'Name', 'id' => 'print'], 'args' => []]];
    if (strpos($code, 'for ') !== false) $ast['body'][] = ['type' => 'For', 'target' => ['type' => 'Name', 'id' => 'i'], 'iter' => ['type' => 'Call', 'func' => ['type' => 'Name', 'id' => 'range'], 'args' => [['type' => 'Num', 'n' => 5]]]];
    return ['ast' => $ast, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulatePythonSymbolTable($code) {
    $symbols = ['functions' => [], 'variables' => []];
    $errors = [];
    if (preg_match('/def\s+(\w+)/', $code, $m)) $symbols['functions'][] = $m[1];
    if (preg_match_all('/\b(\w+)\s*=/', $code, $m)) foreach ($m[1] as $var) if (!in_array($var, ['self', 'cls'])) $symbols['variables'][] = $var;
    return ['symbol_table' => $symbols, 'errors' => $errors, 'has_errors' => false];
}

function simulatePythonBytecode($code) {
    $bytecode = [];
    $bytecode[] = '; Python Bytecode (.pyc)';
    $bytecode[] = '; Source: ' . substr($code, 0, 50) . '...';
    $bytecode[] = '';
    $bytecode[] = '  1           0 LOAD_CONST               0 (10)';
    $bytecode[] = '              2 STORE_NAME               0 (x)';
    $bytecode[] = '  2           4 LOAD_CONST               1 (20)';
    $bytecode[] = '              6 STORE_NAME               1 (y)';
    if (strpos($code, '+') !== false) {
        $bytecode[] = '  3           8 LOAD_NAME                0 (x)';
        $bytecode[] = '             10 LOAD_NAME                1 (y)';
        $bytecode[] = '             12 BINARY_ADD';
        $bytecode[] = '             14 STORE_NAME               2 (result)';
    }
    if (strpos($code, 'print') !== false) {
        $bytecode[] = '  4          16 LOAD_NAME                3 (print)';
        $bytecode[] = '             18 LOAD_CONST               2 (\'Result:\')';
        $bytecode[] = '             20 LOAD_NAME                2 (result)';
        $bytecode[] = '             22 CALL_FUNCTION            2';
        $bytecode[] = '             24 POP_TOP';
    }
    if (strpos($code, 'for') !== false) {
        $bytecode[] = '  5          26 SETUP_LOOP              20 (to 48)';
        $bytecode[] = '             28 LOAD_NAME                4 (range)';
        $bytecode[] = '             30 LOAD_CONST               3 (5)';
        $bytecode[] = '             32 CALL_FUNCTION            1';
        $bytecode[] = '             34 GET_ITER';
        $bytecode[] = '        >>   36 FOR_ITER                 8 (to 46)';
        $bytecode[] = '             38 STORE_NAME               5 (i)';
        $bytecode[] = '             40 LOAD_NAME                3 (print)';
        $bytecode[] = '             42 LOAD_NAME                5 (i)';
        $bytecode[] = '             44 CALL_FUNCTION            1';
        $bytecode[] = '             46 POP_TOP';
        $bytecode[] = '             48 JUMP_ABSOLUTE           36';
        $bytecode[] = '             50 POP_BLOCK';
    }
    if (strpos($code, 'if') !== false && strpos($code, 'else') !== false) {
        $bytecode[] = '  6          52 LOAD_NAME                2 (result)';
        $bytecode[] = '             54 LOAD_CONST               4 (25)';
        $bytecode[] = '             56 COMPARE_OP               4 (>)';
        $bytecode[] = '             58 POP_JUMP_IF_FALSE       70';
        $bytecode[] = '  7          60 LOAD_NAME                3 (print)';
        $bytecode[] = '             62 LOAD_CONST               5 (\'Result is greater than 25\')';
        $bytecode[] = '             64 CALL_FUNCTION            1';
        $bytecode[] = '             66 POP_TOP';
        $bytecode[] = '             68 JUMP_FORWARD             8 (to 78)';
        $bytecode[] = '  9     >>   70 LOAD_NAME                3 (print)';
        $bytecode[] = '             72 LOAD_CONST               6 (\'Result is 25 or less\')';
        $bytecode[] = '             74 CALL_FUNCTION            1';
        $bytecode[] = '             76 POP_TOP';
        $bytecode[] = '  10     >>   78 LOAD_CONST               7 (None)';
        $bytecode[] = '             80 RETURN_VALUE';
    }
    $bytecode[] = '  5          82 LOAD_CONST               7 (None)';
    $bytecode[] = '             84 RETURN_VALUE';
    return ['bytecode' => $bytecode, 'errors' => [], 'has_errors' => false];
}

function simulatePythonExecution($code) {
    $output = '';
    if (strpos($code, 'print') !== false) {
        if (strpos($code, 'Result:') !== false) $output = "Result: 30\n";
        else $output = "Hello from Python!\n";
    }
    if (strpos($code, 'for') !== false) {
        $output .= "Iteration 0\nIteration 1\nIteration 2\nIteration 3\nIteration 4\n";
    }
    if (strpos($code, 'if') !== false && strpos($code, 'else') !== false) {
        $output .= "Result is greater than 25\n";
    }
    return ['execution' => ['output' => $output, 'exit_code' => 0], 'errors' => [], 'has_errors' => false];
}

function generatePythonVisualizationData($result, $stage, $view) {
    $nodes = []; $edges = [];
    if ($view === 'pipeline') {
        $stages = [
            ['id' => 'lexical', 'name' => 'Lexical Analysis', 'x' => -30, 'y' => 0, 'z' => 0],
            ['id' => 'syntax', 'name' => 'Syntax Analysis', 'x' => -18, 'y' => 0, 'z' => 0],
            ['id' => 'semantic', 'name' => 'Semantic Analysis', 'x' => -6, 'y' => 0, 'z' => 0],
            ['id' => 'bytecode', 'name' => 'Bytecode Compilation', 'x' => 6, 'y' => 0, 'z' => 0],
            ['id' => 'pvm', 'name' => 'Interpretation (PVM)', 'x' => 18, 'y' => 0, 'z' => 0],
        ];
        foreach ($stages as $info) {
            $stage_data = null;
            foreach ($result['stages'] as $s) if (strpos(strtolower($s['name']), strtolower($info['id'])) !== false) { $stage_data = $s; break; }
            $has_errors = $stage_data && !empty($stage_data['errors']);
            $nodes[] = ['id' => $info['id'], 'name' => $info['name'], 'type' => 'stage', 'color' => $has_errors ? '#e74c3c' : '#2ecc71', 'position' => ['x' => $info['x'], 'y' => $info['y'], 'z' => $info['z']], 'size' => 3, 'status' => $stage_data['status'] ?? 'pending', 'duration' => $stage_data['duration'] ?? 0, 'has_errors' => $has_errors, 'error_count' => $has_errors ? count($stage_data['errors']) : 0];
        }
        for ($i=0; $i<count($stages)-1; $i++) $edges[] = ['from' => $stages[$i]['id'], 'to' => $stages[$i+1]['id'], 'type' => 'pipeline', 'color' => '#3498db'];
    } elseif ($view === 'ast') {
        if (isset($result['outputs']['ast'])) { $ast = $result['outputs']['ast']; $node_id=0; createPythonASTNodes($ast, $nodes, $edges, $node_id, 0,10,0,-1); }
    } elseif ($view === 'memory') {
        $nodes[] = ['id' => 'heap', 'name' => 'Heap (Objects)', 'x' => -15, 'y' => 10, 'z' => 0, 'type' => 'memory', 'color' => '#3498db', 'size' => 4, 'height' => 5];
        $nodes[] = ['id' => 'stack', 'name' => 'Call Stack', 'x' => 15, 'y' => 10, 'z' => 0, 'type' => 'memory', 'color' => '#e74c3c', 'size' => 3, 'height' => 6];
        $nodes[] = ['id' => 'frame_main', 'name' => 'main frame', 'x' => 15, 'y' => 0, 'z' => 5, 'size' => 1.5, 'color' => '#9b59b6'];
    }
    return ['nodes' => $nodes, 'edges' => $edges, 'view' => $view, 'stage' => $stage, 'session_id' => $result['session_id'] ?? ''];
}

function createPythonASTNodes($node, &$nodes, &$edges, &$node_id, $x, $y, $z, $parent_id) {
    $current_id = $node_id++;
    $node_type = $node['type'] ?? 'Node';
    $node_name = $node['name'] ?? $node_type;
    $nodes[] = ['id' => 'ast_node_' . $current_id, 'name' => $node_name, 'type' => $node_type, 'color' => '#2ecc71', 'position' => ['x' => $x, 'y' => $y, 'z' => $z], 'size' => 2, 'has_errors' => false, 'error_count' => 0];
    if ($parent_id >= 0) $edges[] = ['from' => 'ast_node_' . $parent_id, 'to' => 'ast_node_' . $current_id, 'type' => 'parent_child', 'color' => '#00ff9d'];
    $child_index = 0;
    foreach ($node as $key => $value) if (is_array($value) && isset($value['type'])) {
        $child_x = $x + 15; $child_y = $y - 8 - ($child_index*8); $child_z = $z + ($child_index*5);
        createPythonASTNodes($value, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id);
        $child_index++;
    }
    return $current_id;
}

// ---------- HTML output (standalone, same style as go.php) ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>3D Python Interpreter Visualizer - Final Project</title>
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
        
        .python-key {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            padding: 10px;
            background: rgba(10, 25, 47, 0.5);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .python-key-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            color: #8892b0;
        }
        
        .python-key-item .key {
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
            <h1><i class="fab fa-python"></i> 3D Python Interpreter Visualizer
                <span class="interpreted-badge"><i class="fas fa-magic"></i> Interpreted Language</span>
            </h1>
            <div class="subtitle">Interactive visualization of Python compilation to bytecode & execution by PVM</div>
        </div>
        <div class="course-info">
            <span class="university">COMPUTER SCIENCE FINAL PROJECT - PYTHON VERSION</span>
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
                    <h2>Python Compilation & Interpretation Controls</h2>
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
                            <option value="bytecode">Bytecode Compilation</option>
                            <option value="pvm">Interpretation (PVM)</option>
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
                    
                    <div class="python-key">
                        <div class="python-key-item"><span class="key">def</span> Function</div>
                        <div class="python-key-item"><span class="key">if/elif/else</span> Conditional</div>
                        <div class="python-key-item"><span class="key">for/while</span> Loop</div>
                        <div class="python-key-item"><span class="key">print()</span> Output</div>
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
                        <h2>Python Source Code Editor</h2>
                    </div>
                    
                    <div class="code-editor">
                        <div class="code-editor-header">
                            <h3><i class="fab fa-python"></i> Python Code</h3>
                            <select id="example-select">
                                <option value="">Load Example...</option>
                                <option value="simple">Simple Math</option>
                                <option value="loop">For Loop</option>
                                <option value="conditional">If-Else</option>
                                <option value="function">Function Definition</option>
                            </select>
                        </div>
                        <textarea id="source-code" spellcheck="false"># Python is interpreted - source code → bytecode → PVM execution
x = 10
y = 20
result = x + y

print("Result:", result)

for i in range(5):
    print("Iteration", i)

if result > 25:
    print("Result is greater than 25")
else:
    print("Result is 25 or less")</textarea>
                    </div>
                    
                    <div class="button-group" style="margin-top: 15px;">
                        <button id="compile-btn" class="btn">
                            <i class="fas fa-play"></i> Compile & Interpret
                        </button>
                        <button id="reset-btn" class="btn danger">
                            <i class="fas fa-trash"></i> Reset
                        </button>
                    </div>
                    
                    <div class="status-bar">
                        <div id="status-message">
                            <i class="fas fa-info-circle"></i> Ready to interpret Python code
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
                    <h2>Python Compilation & Execution Output</h2>
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
                        <button class="output-tab" data-output="bytecode">
                            <i class="fas fa-microchip"></i> <span class="tab-text">Bytecode</span>
                        </button>
                        <button class="output-tab" data-output="execution">
                            <i class="fas fa-play-circle"></i> <span class="tab-text">Execution Output</span>
                        </button>
                        <button class="output-tab" data-output="errors" id="errors-tab" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> <span class="tab-text">Errors</span>
                        </button>
                    </div>
                    
                    <!-- Output display area -->
                    <div id="tokens-output" class="output-display active"></div>
                    <div id="ast-output" class="output-display"></div>
                    <div id="bytecode-output" class="output-display"></div>
                    <div id="execution-output" class="output-display"></div>
                    <div id="errors-output" class="output-display"></div>
                    
                    <div class="panel-header" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <h2>Stage Information</h2>
                    </div>
                    
                    <div id="stage-info" class="stage-info">
                        <p><i class="fas fa-mouse-pointer"></i> Python is an interpreted language: source → bytecode → PVM execution.</p>
                        <div style="margin-top: 12px; padding: 12px; background: rgba(100,255,218,0.05); border-radius: 5px; border: 1px dashed rgba(100,255,218,0.2);">
                            <p style="color: #64ffda; margin-bottom: 6px; font-size: 0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Key Distinction:</strong></p>
                            <p style="font-size: 0.8rem; color: #8892b0;">Unlike compiled languages (C, Go), Python code is first compiled to bytecode (.pyc) then interpreted by the Python Virtual Machine (PVM). This allows platform independence but at the cost of slower execution.</p>
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
                        <button id="download-bytecode" class="btn secondary">
                            <i class="fas fa-download"></i> <span class="btn-text">Bytecode</span>
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
                <span>Final Year Project - Computer Science Department | Python Bytecode & PVM 3D Visualization System</span>
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
            <a href="https://github.com/Agabaofficial/python-compiler-3d-visualizer" target="_blank" class="github-link">
                <i class="fab fa-github"></i> View on GitHub
            </a>
        </div>
    </div>
    
    <div id="tooltip" class="tooltip"></div>

    <script>
        class PythonInterpreterVisualizer3D {
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
                document.getElementById('download-bytecode').addEventListener('click', () => this.download('bytecode'));
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
                if (!sourceCode.trim()) { this.showStatus('Please enter Python source code', 'error'); return; }
                this.showStatus('Compiling to bytecode & interpreting...', 'info');
                this.updateProgress(10);
                try {
                    const response = await fetch('?api=compile', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ source_code: sourceCode })
                    });
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
                    this.showStatus(data.success ? 'Python interpretation successful!' : 'Compilation completed with warnings', data.success ? 'success' : 'warning');
                } catch (error) { this.showStatus('Error: ' + error.message, 'error'); this.updateProgress(0); console.error(error); }
            }
            
            updateOutputs(data) {
                if (data.outputs && data.outputs.tokens) {
                    const tokensText = data.outputs.tokens.map(t => `Line ${t.line}: ${t.type.padEnd(15)} = "${t.value}"`).join('\n');
                    document.getElementById('tokens-output').textContent = tokensText;
                }
                if (data.outputs && data.outputs.ast) {
                    document.getElementById('ast-output').textContent = JSON.stringify(data.outputs.ast, null, 2);
                }
                if (data.outputs && data.outputs.bytecode) {
                    document.getElementById('bytecode-output').textContent = Array.isArray(data.outputs.bytecode) ? data.outputs.bytecode.join('\n') : data.outputs.bytecode;
                }
                if (data.outputs && data.outputs.execution_output) {
                    document.getElementById('execution-output').textContent = data.outputs.execution_output.output;
                }
                if (data.errors && data.errors.length > 0) {
                    const errorsText = data.errors.map((err, idx) => `${idx+1}. ${err.type || 'Error'}:\n   ${err.message}\n   Line: ${err.line || 'N/A'}\n`).join('\n');
                    document.getElementById('errors-output').textContent = errorsText;
                    let errorHtml = '';
                    data.errors.forEach((error) => { const icon = error.severity === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'; errorHtml += `<div class="error-item"><div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;"><i class="fas ${icon}"></i> <strong>${error.type || 'Error'}:</strong> ${error.message}</div>${error.line ? `<div style="font-size:0.85rem; color:#ffd166;">Line ${error.line}</div>` : ''}</div>`; });
                    document.getElementById('error-list').innerHTML = errorHtml;
                    document.getElementById('error-section').style.display = 'block';
                } else { document.getElementById('error-section').style.display = 'none'; }
                if (data.stages && data.stages.length > 0) {
                    let stageHtml = '<h3 style="color:#64ffda; margin-bottom:12px; font-size:1rem;">Python Pipeline</h3>';
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
                for (let i = 0; i < 5; i++) { const geometry = new THREE.SphereGeometry(3,32,32); const material = new THREE.MeshPhongMaterial({ color: colors[i], emissive: colors[i], emissiveIntensity:0.2, transparent:true, opacity:0.9 }); const sphere = new THREE.Mesh(geometry,material); sphere.position.set(i*12-24,0,0); sphere.castShadow=true; this.scene.add(sphere); this.objects.push(sphere); }
            }
            
            createNode(node) {
                let geometry, material; const color = new THREE.Color(node.color || '#64ffda');
                if (this.currentView === 'memory') { geometry = new THREE.BoxGeometry(node.size||3, node.height||3, node.size||3); }
                else { geometry = new THREE.SphereGeometry(node.size||2.5,32,32); }
                material = new THREE.MeshPhongMaterial({ color:color, emissive:color, emissiveIntensity:0.2, transparent:true, opacity:0.9, shininess:100 });
                const mesh = new THREE.Mesh(geometry,material);
                mesh.position.set(node.position?.x||0, node.position?.y||0, node.position?.z||0);
                mesh.castShadow=true; mesh.receiveShadow=true; mesh.userData=node;
                this.scene.add(mesh); this.objects.push(mesh);
                if (node.has_errors && this.showErrors) this.addErrorIndicator(mesh);
            }
            
            addErrorIndicator(mesh) { const node = mesh.userData; const geometry = new THREE.SphereGeometry((node.size||2.5)*1.3,16,16); const material = new THREE.MeshBasicMaterial({ color:0xff6b6b, transparent:true, opacity:0.3, side:THREE.DoubleSide }); const indicator = new THREE.Mesh(geometry,material); indicator.position.copy(mesh.position); this.scene.add(indicator); this.objects.push(indicator); }
            
            createEdge(edge, nodes) {
                const fromNode = this.findObjectById(edge.from); const toNode = this.findObjectById(edge.to);
                if (!fromNode || !toNode) return;
                const curve = new THREE.CatmullRomCurve3([fromNode.position.clone(), new THREE.Vector3((fromNode.position.x+toNode.position.x)/2, (fromNode.position.y+toNode.position.y)/2+8, (fromNode.position.z+toNode.position.z)/2), toNode.position.clone()]);
                const geometry = new THREE.TubeGeometry(curve,20,0.2,8,false);
                const material = new THREE.MeshBasicMaterial({ color: this.getEdgeColor(edge.type), transparent:true, opacity:0.7 });
                const tube = new THREE.Mesh(geometry,material);
                this.scene.add(tube); this.objects.push(tube);
            }
            
            getEdgeColor(type) { const colors = { 'pipeline':0x64ffda, 'parent_child':0x00d9a6, 'control_flow':0xffa502, 'memory_adjacent':0x9b59b6 }; return colors[type]||0x8892b0; }
            
            addLabels(nodes) {
                this.labels.forEach(label => this.scene.remove(label)); this.labels = [];
                nodes.forEach(node => {
                    if (node.type === 'memory') return;
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
            reset() { this.sessionId=null; this.totalErrors=0; document.getElementById('source-code').value=`# Python is interpreted - source code → bytecode → PVM execution\nx = 10\ny = 20\nresult = x + y\n\nprint("Result:", result)\n\nfor i in range(5):\n    print("Iteration", i)\n\nif result > 25:\n    print("Result is greater than 25")\nelse:\n    print("Result is 25 or less")`; document.getElementById('tokens-output').textContent=''; document.getElementById('ast-output').textContent=''; document.getElementById('bytecode-output').textContent=''; document.getElementById('execution-output').textContent=''; document.getElementById('errors-output').textContent=''; this.showOutput('tokens'); document.getElementById('errors-tab').style.display='none'; document.getElementById('error-section').style.display='none'; document.getElementById('stage-info').innerHTML=`<p><i class="fas fa-mouse-pointer"></i> Python is an interpreted language: source → bytecode → PVM execution.</p><div style="margin-top:12px; padding:12px; background:rgba(100,255,218,0.05); border-radius:5px; border:1px dashed rgba(100,255,218,0.2);"><p style="color:#64ffda; margin-bottom:6px; font-size:0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Key Distinction:</strong></p><p style="font-size:0.8rem; color:#8892b0;">Unlike compiled languages (C, Go), Python code is first compiled to bytecode (.pyc) then interpreted by the Python Virtual Machine (PVM). This allows platform independence but at the cost of slower execution.</p></div>`; this.clearScene(); this.createDefaultVisualization(); this.updateProgress(0); this.showStatus('Ready to interpret Python code'); }
            clearScene() { this.objects.forEach(obj=>this.scene.remove(obj)); this.objects=[]; this.labels.forEach(label=>this.scene.remove(label)); this.labels=[]; }
            resetView() { this.camera.position.set(0,30,50); this.controls.reset(); }
            zoom(factor) { this.camera.position.multiplyScalar(factor); this.controls.update(); }
            takeScreenshot() { this.renderer.render(this.scene,this.camera); const link=document.createElement('a'); link.href=this.renderer.domElement.toDataURL('image/png'); link.download=`python_interpreter_visualization_${Date.now()}.png`; link.click(); }
            async download(type) { if(!this.sessionId){ this.showStatus('No compilation session found','error'); return; } this.showStatus(`Downloading ${type}...`,'info'); try{ const response=await fetch(`?api=download&session_id=${this.sessionId}&type=${type}`); if(!response.ok) throw new Error('Download failed'); const blob=await response.blob(); const url=window.URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download=type==='bytecode'?`bytecode_${this.sessionId}.pyc`:type==='ast'?`ast_${this.sessionId}.json`:`script_${this.sessionId}.py`; document.body.appendChild(a); a.click(); document.body.removeChild(a); window.URL.revokeObjectURL(url); this.showStatus(`${type} downloaded successfully`,'success'); } catch(error){ this.showStatus('Download failed: '+error.message,'error'); } }
            loadExample(exampleId) { const examples={ simple:`x = 10\ny = 20\nresult = x + y\nprint("Result:", result)`, loop:`for i in range(5):\n    print("Iteration", i)`, conditional:`score = 85\nif score >= 90:\n    print("Grade: A")\nelif score >= 80:\n    print("Grade: B")\nelse:\n    print("Grade: C")`, function:`def greet(name):\n    return f"Hello, {name}!"\nprint(greet("Python"))` }; if(examples[exampleId]){ document.getElementById('source-code').value=examples[exampleId]; this.showStatus(`Loaded Python example: ${exampleId}`,'success'); } document.getElementById('example-select').value=''; }
            onMouseMove(event) { const rect=this.renderer.domElement.getBoundingClientRect(); this.mouse.x=((event.clientX-rect.left)/rect.width)*2-1; this.mouse.y=-((event.clientY-rect.top)/rect.height)*2+1; this.raycaster.setFromCamera(this.mouse,this.camera); const intersects=this.raycaster.intersectObjects(this.objects); const tooltip=document.getElementById('tooltip'); if(intersects.length>0){ const data=intersects[0].object.userData; if(data){ tooltip.style.display='block'; tooltip.style.left=(event.clientX+10)+'px'; tooltip.style.top=(event.clientY+10)+'px'; let html=`<div style="color:#64ffda; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">${this.truncateText(data.name,25)}</div>`; html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Type:</strong> ${data.type}</div>`; if(data.status) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Status:</strong> ${data.status}</div>`; if(data.duration) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Duration:</strong> ${data.duration}ms</div>`; if(data.error_count>0) html+=`<div style="color:#ff6b6b; margin-top:6px; font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> ${data.error_count} error(s)</div>`; tooltip.innerHTML=html; } } else { tooltip.style.display='none'; } }
            handleResize() { const container=document.getElementById('visualization-canvas'); this.camera.aspect=container.clientWidth/container.clientHeight; this.camera.updateProjectionMatrix(); this.renderer.setSize(container.clientWidth,container.clientHeight); this.isMobile=window.innerWidth<992; }
            animate() { this.animationId=requestAnimationFrame(()=>this.animate()); if(this.autoRotate) this.scene.rotation.y+=0.001; this.controls.update(); this.renderer.render(this.scene,this.camera); }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            window.pythonViz = new PythonInterpreterVisualizer3D();
            if (window.innerWidth < 768) {
                document.querySelectorAll('.tab-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
                document.querySelectorAll('.btn-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
            }
            if (window.innerWidth < 768) { const textarea = document.getElementById('source-code'); textarea.style.minHeight = '150px'; }
        });
    </script>
</body>
</html>