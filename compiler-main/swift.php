<?php
// swift.php - Swift Compiler Visualizer with Authentication & Logging

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'swift_errors.log');

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
            case 'compile': $result = handleSwiftCompile(); break;
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

function handleSwiftCompile() {
    $input = file_get_contents('php://input');
    if (empty($input)) return ['error' => 'No input data received'];
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['error' => 'Invalid JSON input: ' . json_last_error_msg()];
    $source_code = $data['source_code'] ?? '';
    if (empty($source_code)) return ['error' => 'No source code provided'];
    $session_id = uniqid('swift_compile_', true);
    $tmp_dir = "tmp/{$session_id}";
    if (!mkdir($tmp_dir, 0777, true) && !is_dir($tmp_dir)) return ['error' => 'Failed to create temporary directory'];
    if (file_put_contents("{$tmp_dir}/main.swift", $source_code) === false) return ['error' => 'Failed to save source code'];
    $result = ['session_id' => $session_id, 'success' => true, 'stages' => [], 'outputs' => [], 'errors' => [], 'source_code' => $source_code];
    $lexicalResult = simulateSwiftTokens($source_code);
    $result['stages'][] = ['name' => 'Lexical Analysis', 'status' => $lexicalResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(50,200), 'tokens' => $lexicalResult['tokens'], 'errors' => $lexicalResult['errors']];
    $syntaxResult = simulateSwiftAST($source_code);
    $result['stages'][] = ['name' => 'Syntax Analysis', 'status' => $syntaxResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(100,300), 'ast' => $syntaxResult['ast'], 'errors' => $syntaxResult['errors']];
    $semanticResult = simulateSwiftSymbolTable($source_code);
    $result['stages'][] = ['name' => 'Semantic Analysis', 'status' => $semanticResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(80,250), 'symbol_table' => $semanticResult['symbol_table'], 'errors' => $semanticResult['errors']];
    $silResult = simulateSwiftSIL($source_code);
    $result['stages'][] = ['name' => 'SIL Generation', 'status' => $silResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(150,400), 'sil_code' => $silResult['sil'], 'errors' => $silResult['errors']];
    $optResult = simulateSwiftOptimization($source_code);
    $result['stages'][] = ['name' => 'Optimization', 'status' => $optResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(200,500), 'optimizations' => $optResult['optimizations'], 'errors' => $optResult['errors']];
    $irResult = simulateLLVMIR($source_code);
    $result['stages'][] = ['name' => 'LLVM IR Generation', 'status' => $irResult['has_errors'] ? 'failed' : 'completed', 'duration' => rand(250,600), 'llvm_ir' => $irResult['llvm_ir'], 'errors' => $irResult['errors']];
    $all_errors = [];
    foreach ($result['stages'] as $stage) if (!empty($stage['errors'])) { $all_errors = array_merge($all_errors, $stage['errors']); $result['success'] = false; }
    $result['errors'] = $all_errors;
    $result['outputs'] = ['tokens' => $result['stages'][0]['tokens'], 'ast' => $result['stages'][1]['ast'], 'sil' => $result['stages'][3]['sil_code'], 'llvm_ir' => $result['stages'][5]['llvm_ir']];
    file_put_contents("{$tmp_dir}/result.json", json_encode($result, JSON_PRETTY_PRINT));
    
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $language = 'swift';
    $success = $result['success'];
    $errorsCount = count($result['errors']);
    $compilationTimeMs = array_sum(array_column($result['stages'], 'duration'));
    logCompilation($userId, $language, $session_id, $source_code, $success, $errorsCount, $compilationTimeMs);
    if ($userId) logActivity($userId, 'compile', "Compiled Swift code, session: $session_id, errors: $errorsCount");
    
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
    $viz_data = generateSwiftVisualizationData($result, $stage, $view);
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
            $file = "{$tmp_dir}/main.swift";
            $filename = "main_{$session_id}.swift";
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
        case 'llvm':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) { http_response_code(404); echo json_encode(['error' => 'File not found']); exit; }
            $result = json_decode(file_get_contents($result_file), true);
            $llvm_ir = $result['outputs']['llvm_ir'] ?? [];
            $content = is_array($llvm_ir) ? implode("\n", $llvm_ir) : $llvm_ir;
            $filename = "llvm_{$session_id}.ll";
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

// Swift-specific functions
function simulateSwiftTokens($code) {
    $tokens = [];
    $errors = [];
    
    $code = preg_replace('/\/\/.*$/m', '', $code);
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    
    $lines = explode("\n", $code);
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, 'import ') === 0) {
            $tokens[] = ['type' => 'KEYWORD', 'value' => 'import', 'line' => $lineNum + 1];
            $module = trim(substr($line, 6));
            $tokens[] = ['type' => 'MODULE', 'value' => $module, 'line' => $lineNum + 1];
            continue;
        }
        
        $quoteCount = substr_count($line, '"');
        if ($quoteCount % 2 != 0) {
            $errors[] = ['type' => 'lexical', 'message' => 'Unterminated string literal', 'line' => $lineNum + 1, 'severity' => 'error'];
        }
        
        $words = preg_split('/(\s+|(?<=[(){}\[\];=+\-\/*<>!,\.:])|(?=[(){}\[\];=+\-\/*<>!,\.:]))/', $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        
        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            $token = ['type' => 'IDENTIFIER', 'value' => $word, 'line' => $lineNum + 1];
            $keywords = ['import', 'var', 'let', 'func', 'class', 'struct', 'enum', 'protocol',
                        'extension', 'if', 'else', 'for', 'while', 'repeat', 'switch', 'case',
                        'default', 'break', 'continue', 'return', 'in', 'where', 'guard',
                        'defer', 'do', 'catch', 'throw', 'throws', 'rethrows', 'try', 'async',
                        'await', 'inout', 'mutating', 'nonmutating', 'static', 'final',
                        'open', 'public', 'private', 'internal', 'fileprivate', 'override',
                        'required', 'convenience', 'lazy', 'weak', 'unowned', 'some', 'any',
                        'self', 'super', 'nil', 'true', 'false', 'Type', 'Protocol',
                        'print', 'println', 'Int', 'String', 'Double', 'Float', 'Bool',
                        'Array', 'Dictionary', 'Set', 'Optional', 'Void', 'Any', 'AnyObject'];
            if (in_array($word, $keywords)) $token['type'] = 'KEYWORD';
            elseif (is_numeric($word)) $token['type'] = 'LITERAL';
            elseif (preg_match('/^".*"$/', $word) || preg_match("/^'.*'$/", $word)) $token['type'] = 'STRING_LITERAL';
            elseif (preg_match('/^[+\-*/=<>!&|]+$/', $word)) $token['type'] = 'OPERATOR';
            elseif (preg_match('/^[(){}\[\];,\.:]$/', $word)) $token['type'] = 'PUNCTUATOR';
            $tokens[] = $token;
        }
    }
    return ['tokens' => $tokens, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateSwiftAST($code) {
    $errors = [];
    $lines = explode("\n", $code);
    $brace_count = 0; $paren_count = 0; $bracket_count = 0;
    $in_class = false; $in_func = false;
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '//') === 0 || strpos($line, '/*') === 0) continue;
        $brace_count += substr_count($line, '{'); $brace_count -= substr_count($line, '}');
        $paren_count += substr_count($line, '('); $paren_count -= substr_count($line, ')');
        $bracket_count += substr_count($line, '['); $bracket_count -= substr_count($line, ']');
        if (preg_match('/^\s*func\s+\w+/', $line)) $in_func = true;
        if (preg_match('/^\s*(class|struct|enum)\s+\w+/', $line)) $in_class = true;
        if (strpos($line, '}') !== false) { if ($brace_count == 0) { $in_func = false; $in_class = false; } }
    }
    if ($brace_count != 0) $errors[] = ['type' => 'syntax', 'message' => 'Unbalanced braces', 'severity' => 'error'];
    if ($paren_count != 0) $errors[] = ['type' => 'syntax', 'message' => 'Unbalanced parentheses', 'severity' => 'error'];
    if ($bracket_count != 0) $errors[] = ['type' => 'syntax', 'message' => 'Unbalanced brackets', 'severity' => 'error'];
    
    $ast = [
        'type' => 'SourceFile',
        'statements' => [
            ['type' => 'ImportDeclaration', 'module' => 'Foundation'],
            ['type' => 'FunctionDeclaration', 'name' => 'main', 'modifiers' => [], 'parameters' => [], 'returnType' => 'Void', 'body' => [
                ['type' => 'VariableDeclaration', 'name' => 'x', 'mutability' => 'let', 'typeAnnotation' => 'Int', 'initialValue' => ['type' => 'IntegerLiteral', 'value' => 10]],
                ['type' => 'VariableDeclaration', 'name' => 'y', 'mutability' => 'let', 'typeAnnotation' => 'Int', 'initialValue' => ['type' => 'IntegerLiteral', 'value' => 20]],
                ['type' => 'VariableDeclaration', 'name' => 'result', 'mutability' => 'let', 'typeAnnotation' => 'Int', 'initialValue' => ['type' => 'BinaryExpression', 'operator' => '+', 'left' => ['type' => 'Identifier', 'name' => 'x'], 'right' => ['type' => 'Identifier', 'name' => 'y']]],
                ['type' => 'FunctionCall', 'functionName' => 'print', 'arguments' => [['type' => 'StringInterpolation', 'parts' => [['type' => 'StringLiteral', 'value' => 'Result: '], ['type' => 'Identifier', 'name' => 'result']]]]]
            ]],
            ['type' => 'FunctionCall', 'functionName' => 'main', 'arguments' => []]
        ]
    ];
    if (strpos($code, 'for ') !== false) {
        $ast['statements'][1]['body'][] = ['type' => 'ForLoop', 'variable' => 'i', 'range' => ['type' => 'RangeExpression', 'start' => ['type' => 'IntegerLiteral', 'value' => 0], 'end' => ['type' => 'IntegerLiteral', 'value' => 5], 'operator' => '..<'], 'body' => [['type' => 'FunctionCall', 'functionName' => 'print', 'arguments' => [['type' => 'StringInterpolation', 'parts' => [['type' => 'StringLiteral', 'value' => 'Iteration '], ['type' => 'Identifier', 'name' => 'i']]]]]]];
    }
    if (strpos($code, 'if ') !== false && strpos($code, 'else') !== false) {
        $ast['statements'][1]['body'][] = ['type' => 'IfStatement', 'condition' => ['type' => 'BinaryExpression', 'operator' => '>', 'left' => ['type' => 'Identifier', 'name' => 'result'], 'right' => ['type' => 'IntegerLiteral', 'value' => 25]], 'then' => [['type' => 'FunctionCall', 'functionName' => 'print', 'arguments' => [['type' => 'StringLiteral', 'value' => 'Result is greater than 25']]]], 'else' => [['type' => 'FunctionCall', 'functionName' => 'print', 'arguments' => [['type' => 'StringLiteral', 'value' => 'Result is 25 or less']]]]];
    }
    return ['ast' => $ast, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateSwiftSymbolTable($code) {
    $symbol_table = ['functions' => [], 'variables' => [], 'types' => [], 'imports' => []];
    $errors = []; $lines = explode("\n", $code); $current_scope = 'global';
    $declared_vars = []; $declared_funcs = []; $declared_types = [];
    $swift_library = ['Foundation', 'UIKit', 'AppKit', 'SwiftUI', 'CoreData', 'Combine', 
                     'print', 'Int', 'String', 'Double', 'Float', 'Bool', 'Array', 'Dictionary',
                     'Set', 'Optional', 'nil', 'Void', 'Any', 'AnyObject', 'Error', 'Result',
                     'Codable', 'Equatable', 'Comparable', 'Hashable', 'CaseIterable', 'Identifiable'];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        $line_without_strings = preg_replace('/"[^"]*"/', 'STRING', $line);
        $line_without_strings = preg_replace('/\\\\\([^)]+\)/', 'INTERPOLATION', $line_without_strings);
        if (strpos($line, '//') === 0 || strpos($line, '/*') === 0) continue;
        if (strpos($line, 'import ') === 0) {
            $import = trim(substr($line, 6));
            $symbol_table['imports'][] = $import;
            $declared_vars[] = $import; $declared_funcs[] = $import; $declared_types[] = $import;
            continue;
        }
        if (preg_match('/^\s*func\s+(\w+)\s*(\([^)]*\))?/', $line_without_strings, $matches)) {
            $func_name = $matches[1]; $current_scope = $func_name; $declared_funcs[] = $func_name;
            $symbol_table['functions'][$func_name] = ['name' => $func_name, 'parameters' => [], 'returnType' => 'Void', 'scope' => $current_scope, 'line' => $lineNum + 1];
        }
        if (preg_match('/^\s*(let|var)\s+(\w+)\s*(:\s*\w+)?\s*(=\s*[^;]+)?/', $line_without_strings, $matches)) {
            $var_name = $matches[2]; $mutability = $matches[1]; $declared_vars[] = $var_name;
            $symbol_table['variables'][] = ['name' => $var_name, 'mutability' => $mutability, 'type' => isset($matches[3]) ? trim(trim($matches[3]), ': ') : 'inferred', 'initialized' => isset($matches[4]), 'scope' => $current_scope, 'line' => $lineNum + 1];
        }
        if (preg_match('/^\s*(class|struct|enum)\s+(\w+)/', $line_without_strings, $matches)) {
            $type_name = $matches[2]; $declared_types[] = $type_name;
            $symbol_table['types'][$type_name] = ['name' => $type_name, 'kind' => $matches[1], 'properties' => [], 'methods' => [], 'line' => $lineNum + 1];
        }
        if (strpos($line, 'import ') === false && preg_match_all('/\b([a-zA-Z_$][a-zA-Z0-9_$]*)\b/', $line_without_strings, $matches)) {
            foreach ($matches[1] as $identifier) {
                if (in_array($identifier, $swift_library) || in_array($identifier, ['func','var','let','class','struct','enum','if','else','for','while','return','in','self','print','import','INTERPOLATION','STRING','async','await','try','catch','throw','throws']) || $identifier === 'INTERPOLATION' || $identifier === 'STRING' || is_numeric($identifier)) continue;
                if (!in_array($identifier, $declared_vars) && !in_array($identifier, $declared_funcs) && !in_array($identifier, $declared_types)) {
                    if (!preg_match('/\\\\\(/', $line) || !preg_match('/\b' . preg_quote($identifier, '/') . '\b/', $line)) {
                        $errors[] = ['type' => 'semantic', 'message' => "Undeclared identifier: $identifier", 'line' => $lineNum + 1, 'severity' => 'warning'];
                    }
                }
            }
        }
    }
    return ['symbol_table' => $symbol_table, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateSwiftSIL($code) {
    $sil = []; $errors = [];
    $lines = explode("\n", $code);
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '//') === 0 || strpos($line, '/*') === 0 || strpos($line, 'import ') === 0) continue;
        if (preg_match('/let\s+(\w+)\s*=\s*(\d+)/', $line, $matches)) {
            $sil[] = ['op' => 'alloc_stack', 'type' => '$Int', 'dest' => "%$matches[1]"];
            $sil[] = ['op' => 'integer_literal', 'type' => '$Builtin.Int64', 'value' => $matches[2], 'dest' => "%tmp"];
            $sil[] = ['op' => 'store', 'src' => '%tmp', 'dest' => "%$matches[1]"];
        } elseif (preg_match('/let\s+(\w+)\s*=\s*(\w+)\s*\+\s*(\w+)/', $line, $matches)) {
            $sil[] = ['op' => 'alloc_stack', 'type' => '$Int', 'dest' => "%$matches[1]"];
            $sil[] = ['op' => 'load', 'src' => "%$matches[2]", 'dest' => '%lhs'];
            $sil[] = ['op' => 'load', 'src' => "%$matches[3]", 'dest' => '%rhs'];
            $sil[] = ['op' => 'builtin', 'name' => '"add_Int64"', 'operands' => ['%lhs', '%rhs'], 'dest' => '%sum'];
            $sil[] = ['op' => 'store', 'src' => '%sum', 'dest' => "%$matches[1]"];
        } elseif (preg_match('/print\("(.*)"\)/', $line, $matches)) {
            $sil[] = ['op' => 'string_literal', 'encoding' => 'utf8', 'value' => $matches[1], 'dest' => '%str'];
            $sil[] = ['op' => 'function_ref', 'name' => '"Swift.print"', 'dest' => '%print_fn'];
            $sil[] = ['op' => 'apply', 'function' => '%print_fn', 'args' => ['%str'], 'dest' => '%_'];
        } elseif (preg_match('/print\(".*\\\\\(.*\)"\)/', $line)) {
            $sil[] = ['op' => 'string_literal', 'encoding' => 'utf8', 'value' => 'Result: ', 'dest' => '%prefix'];
            $sil[] = ['op' => 'function_ref', 'name' => '"Swift.print"', 'dest' => '%print_fn'];
            $sil[] = ['op' => 'apply', 'function' => '%print_fn', 'args' => ['%prefix'], 'dest' => '%_'];
        }
    }
    return ['sil' => $sil, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function simulateSwiftOptimization($code) {
    return ['optimizations' => ['ARC optimization', 'Inlining of small functions', 'Dead code elimination', 'Constant propagation', 'String optimization', 'Generic specialization', 'Whole module optimization', 'Memory allocation optimization'], 'errors' => [], 'has_errors' => false];
}

function simulateLLVMIR($code) {
    $llvm_ir = []; $errors = [];
    $llvm_ir[] = '; Generated LLVM IR for Swift code';
    $llvm_ir[] = '; Source: ' . substr($code, 0, 50) . '...';
    $llvm_ir[] = '';
    $llvm_ir[] = 'target datalayout = "e-m:o-i64:64-f80:128-n8:16:32:64-S128"';
    $llvm_ir[] = 'target triple = "x86_64-apple-macosx10.15.0"';
    $llvm_ir[] = '';
    $llvm_ir[] = '@.str = private unnamed_addr constant [14 x i8] c"Hello, Swift!\\00", align 1';
    $llvm_ir[] = '@.str.1 = private unnamed_addr constant [12 x i8] c"Result: %d\\0A\\00", align 1';
    $llvm_ir[] = '@.str.2 = private unnamed_addr constant [13 x i8] c"Iteration %d\\0A\\00", align 1';
    $llvm_ir[] = '@.str.3 = private unnamed_addr constant [28 x i8] c"Result is greater than 25\\0A\\00", align 1';
    $llvm_ir[] = '@.str.4 = private unnamed_addr constant [24 x i8] c"Result is 25 or less\\0A\\00", align 1';
    $llvm_ir[] = '';
    $llvm_ir[] = 'define i32 @main() #0 {';
    $llvm_ir[] = '  %1 = alloca i32, align 4';
    $llvm_ir[] = '  %2 = alloca i32, align 4';
    $llvm_ir[] = '  %3 = alloca i32, align 4';
    $llvm_ir[] = '  %4 = alloca i32, align 4';
    $llvm_ir[] = '  store i32 0, i32* %1, align 4';
    $llvm_ir[] = '';
    if (preg_match('/let\s+x\s*=\s*(\d+)/', $code, $matches)) {
        $llvm_ir[] = '  ; let x = ' . $matches[1];
        $llvm_ir[] = '  store i32 ' . $matches[1] . ', i32* %2, align 4';
        $llvm_ir[] = '';
    }
    if (preg_match('/let\s+y\s*=\s*(\d+)/', $code, $matches)) {
        $llvm_ir[] = '  ; let y = ' . $matches[1];
        $llvm_ir[] = '  store i32 ' . $matches[1] . ', i32* %3, align 4';
        $llvm_ir[] = '';
    }
    if (preg_match('/let\s+result\s*=\s*x\s*\+\s*y/', $code)) {
        $llvm_ir[] = '  ; let result = x + y';
        $llvm_ir[] = '  %5 = load i32, i32* %2, align 4';
        $llvm_ir[] = '  %6 = load i32, i32* %3, align 4';
        $llvm_ir[] = '  %7 = add nsw i32 %5, %6';
        $llvm_ir[] = '  store i32 %7, i32* %4, align 4';
        $llvm_ir[] = '';
    }
    if (strpos($code, 'print(') !== false) {
        $llvm_ir[] = '  ; print statement';
        if (strpos($code, 'Result:') !== false) {
            $llvm_ir[] = '  %8 = load i32, i32* %4, align 4';
            $llvm_ir[] = '  %9 = call i32 (i8*, ...) @printf(i8* getelementptr inbounds ([12 x i8], [12 x i8]* @.str.1, i64 0, i64 0), i32 %8)';
        } else {
            $llvm_ir[] = '  %10 = call i32 (i8*, ...) @printf(i8* getelementptr inbounds ([14 x i8], [14 x i8]* @.str, i64 0, i64 0))';
        }
        $llvm_ir[] = '';
    }
    if (strpos($code, 'for ') !== false) {
        $llvm_ir[] = '  ; for loop';
        $llvm_ir[] = '  br label %11';
        $llvm_ir[] = '';
        $llvm_ir[] = '11:                                               ; preds = %14, %0';
        $llvm_ir[] = '  %12 = phi i32 [ 0, %0 ], [ %15, %14 ]';
        $llvm_ir[] = '  %13 = icmp slt i32 %12, 5';
        $llvm_ir[] = '  br i1 %13, label %14, label %16';
        $llvm_ir[] = '';
        $llvm_ir[] = '14:                                               ; preds = %11';
        $llvm_ir[] = '  %15 = call i32 (i8*, ...) @printf(i8* getelementptr inbounds ([13 x i8], [13 x i8]* @.str.2, i64 0, i64 0), i32 %12)';
        $llvm_ir[] = '  %16 = add nsw i32 %12, 1';
        $llvm_ir[] = '  br label %11';
        $llvm_ir[] = '';
        $llvm_ir[] = '16:                                               ; preds = %11';
        $llvm_ir[] = '';
    }
    if (strpos($code, 'if ') !== false && strpos($code, 'else') !== false) {
        $llvm_ir[] = '  ; if-else statement';
        $llvm_ir[] = '  %17 = load i32, i32* %4, align 4';
        $llvm_ir[] = '  %18 = icmp sgt i32 %17, 25';
        $llvm_ir[] = '  br i1 %18, label %19, label %21';
        $llvm_ir[] = '';
        $llvm_ir[] = '19:                                               ; preds = %16';
        $llvm_ir[] = '  %20 = call i32 (i8*, ...) @printf(i8* getelementptr inbounds ([28 x i8], [28 x i8]* @.str.3, i64 0, i64 0))';
        $llvm_ir[] = '  br label %23';
        $llvm_ir[] = '';
        $llvm_ir[] = '21:                                               ; preds = %16';
        $llvm_ir[] = '  %22 = call i32 (i8*, ...) @printf(i8* getelementptr inbounds ([24 x i8], [24 x i8]* @.str.4, i64 0, i64 0))';
        $llvm_ir[] = '  br label %23';
        $llvm_ir[] = '';
        $llvm_ir[] = '23:                                               ; preds = %21, %19';
        $llvm_ir[] = '';
    }
    $llvm_ir[] = '  ret i32 0';
    $llvm_ir[] = '}';
    $llvm_ir[] = '';
    $llvm_ir[] = 'declare i32 @printf(i8*, ...) #1';
    return ['llvm_ir' => $llvm_ir, 'errors' => $errors, 'has_errors' => !empty($errors)];
}

function generateSwiftVisualizationData($result, $stage, $view) {
    $nodes = []; $edges = [];
    if ($view === 'pipeline') {
        $stages = [['id' => 'lexical', 'name' => 'Lexical Analysis', 'x' => -30, 'y' => 0, 'z' => 0],
                   ['id' => 'syntax', 'name' => 'Syntax Analysis', 'x' => -18, 'y' => 0, 'z' => 0],
                   ['id' => 'semantic', 'name' => 'Semantic Analysis', 'x' => -6, 'y' => 0, 'z' => 0],
                   ['id' => 'sil', 'name' => 'SIL Generation', 'x' => 6, 'y' => 0, 'z' => 0],
                   ['id' => 'optimization', 'name' => 'Optimization', 'x' => 18, 'y' => 0, 'z' => 0],
                   ['id' => 'llvm', 'name' => 'LLVM IR Generation', 'x' => 30, 'y' => 0, 'z' => 0]];
        foreach ($stages as $stage_info) {
            $stage_data = null;
            foreach ($result['stages'] as $s) if (strpos(strtolower($s['name']), strtolower($stage_info['id'])) !== false) { $stage_data = $s; break; }
            $has_errors = $stage_data && !empty($stage_data['errors']);
            $nodes[] = ['id' => $stage_info['id'], 'name' => $stage_info['name'], 'type' => 'stage', 'color' => $has_errors ? '#e74c3c' : '#2ecc71', 'position' => ['x' => $stage_info['x'], 'y' => $stage_info['y'], 'z' => $stage_info['z']], 'size' => 3, 'status' => $stage_data['status'] ?? 'pending', 'duration' => $stage_data['duration'] ?? 0, 'has_errors' => $has_errors, 'error_count' => $has_errors ? count($stage_data['errors']) : 0];
        }
        for ($i = 0; $i < count($stages) - 1; $i++) $edges[] = ['from' => $stages[$i]['id'], 'to' => $stages[$i+1]['id'], 'type' => 'pipeline', 'color' => '#3498db'];
    } elseif ($view === 'ast') {
        if (isset($result['outputs']['ast'])) { $ast = $result['outputs']['ast']; $node_id = 0; createSwiftASTNodes($ast, $nodes, $edges, $node_id, 0, 10, 0, -1); }
    } elseif ($view === 'cfg') {
        $cfg_nodes = [['id' => 'entry', 'name' => 'Entry', 'x' => 0, 'y' => 20, 'z' => 0, 'type' => 'entry'],
                      ['id' => 'decl', 'name' => 'Declarations', 'x' => -10, 'y' => 10, 'z' => 0, 'type' => 'declaration'],
                      ['id' => 'init', 'name' => 'Initialization', 'x' => -10, 'y' => 0, 'z' => 0, 'type' => 'statement'],
                      ['id' => 'cond', 'name' => 'Condition Check', 'x' => 0, 'y' => -10, 'z' => 0, 'type' => 'condition'],
                      ['id' => 'body', 'name' => 'Function Body', 'x' => 10, 'y' => 0, 'z' => 0, 'type' => 'statement'],
                      ['id' => 'exit', 'name' => 'Exit', 'x' => 0, 'y' => -20, 'z' => 0, 'type' => 'exit']];
        foreach ($cfg_nodes as $node) $nodes[] = ['id' => $node['id'], 'name' => $node['name'], 'type' => $node['type'], 'color' => getCFGNodeColor($node['type']), 'position' => ['x' => $node['x'], 'y' => $node['y'], 'z' => $node['z']], 'size' => 2.5, 'has_errors' => false, 'error_count' => 0];
        $cfg_edges = [['from' => 'entry', 'to' => 'decl', 'type' => 'control_flow'], ['from' => 'decl', 'to' => 'init', 'type' => 'control_flow'], ['from' => 'init', 'to' => 'cond', 'type' => 'control_flow'], ['from' => 'cond', 'to' => 'body', 'type' => 'conditional', 'label' => 'true'], ['from' => 'cond', 'to' => 'exit', 'type' => 'conditional', 'label' => 'false'], ['from' => 'body', 'to' => 'exit', 'type' => 'control_flow']];
        foreach ($cfg_edges as $edge) $edges[] = $edge;
    } elseif ($view === 'memory') {
        $memory_sections = [['id' => 'stack', 'name' => 'Stack', 'x' => -20, 'y' => 15, 'z' => 0, 'size' => 4, 'height' => 4],
                            ['id' => 'heap', 'name' => 'Heap', 'x' => -10, 'y' => 15, 'z' => 0, 'size' => 5, 'height' => 6],
                            ['id' => 'text', 'name' => 'Text/Code', 'x' => 0, 'y' => 15, 'z' => 0, 'size' => 3, 'height' => 3],
                            ['id' => 'data', 'name' => 'Data', 'x' => 10, 'y' => 5, 'z' => 0, 'size' => 4, 'height' => 4],
                            ['id' => 'bss', 'name' => 'BSS', 'x' => 20, 'y' => -5, 'z' => 0, 'size' => 3, 'height' => 5]];
        foreach ($memory_sections as $section) $nodes[] = ['id' => $section['id'], 'name' => $section['name'], 'type' => 'memory_section', 'color' => getMemorySectionColor($section['id']), 'position' => ['x' => $section['x'], 'y' => $section['y'], 'z' => $section['z']], 'size' => $section['size'], 'height' => $section['height'], 'has_errors' => false, 'error_count' => 0];
        $allocations = [['id' => 'var_x', 'name' => 'let x = 10', 'x' => -20, 'y' => 5, 'z' => 5, 'size' => 1.5], ['id' => 'var_y', 'name' => 'let y = 20', 'x' => -20, 'y' => 2, 'z' => 5, 'size' => 1.5], ['id' => 'str_const', 'name' => '"Result: "', 'x' => 10, 'y' => 2, 'z' => 5, 'size' => 1.5], ['id' => 'func_main', 'name' => 'main()', 'x' => 0, 'y' => 8, 'z' => 5, 'size' => 2]];
        foreach ($allocations as $alloc) $nodes[] = ['id' => $alloc['id'], 'name' => $alloc['name'], 'type' => 'allocation', 'color' => '#9b59b6', 'position' => ['x' => $alloc['x'], 'y' => $alloc['y'], 'z' => $alloc['z']], 'size' => $alloc['size'], 'has_errors' => false, 'error_count' => 0];
    }
    return ['nodes' => $nodes, 'edges' => $edges, 'view' => $view, 'stage' => $stage, 'session_id' => $result['session_id'] ?? ''];
}

function getMemorySectionColor($section) { $colors = ['stack' => '#3498db', 'heap' => '#2ecc71', 'text' => '#f39c12', 'data' => '#9b59b6', 'bss' => '#e74c3c']; return $colors[$section] ?? '#95a5a6'; }
function getCFGNodeColor($type) { $colors = ['entry' => '#2ecc71', 'exit' => '#e74c3c', 'declaration' => '#3498db', 'condition' => '#f39c12', 'statement' => '#9b59b6']; return $colors[$type] ?? '#95a5a6'; }

function createSwiftASTNodes($node, &$nodes, &$edges, &$node_id, $x, $y, $z, $parent_id) {
    $current_id = $node_id++;
    $node_type = $node['type'] ?? 'Unknown';
    $node_name = $node['name'] ?? $node_type;
    if ($node_type === 'ImportDeclaration') $node_name = "import " . ($node['module'] ?? '');
    elseif ($node_type === 'StringInterpolation') $node_name = 'String Interpolation';
    elseif ($node_type === 'RangeExpression') $node_name = 'Range';
    $nodes[] = ['id' => 'ast_node_' . $current_id, 'name' => $node_name, 'type' => $node_type, 'color' => getSwiftASTNodeColor($node_type), 'position' => ['x' => $x, 'y' => $y, 'z' => $z], 'size' => 2, 'has_errors' => false, 'error_count' => 0];
    if ($parent_id >= 0) $edges[] = ['from' => 'ast_node_' . $parent_id, 'to' => 'ast_node_' . $current_id, 'type' => 'parent_child', 'color' => '#2ecc71'];
    $child_index = 0;
    foreach ($node as $key => $value) {
        if ($key === 'type' || $key === 'name' || $key === 'module') continue;
        if ($key === 'statements' || $key === 'body' || $key === 'arguments' || $key === 'properties' || $key === 'parts' || $key === 'then' || $key === 'else') {
            if (is_array($value)) foreach ($value as $child) if (is_array($child)) { $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5); createSwiftASTNodes($child, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++; }
        } elseif (is_array($value) && isset($value['type'])) {
            $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5);
            createSwiftASTNodes($value, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++;
        } elseif (is_array($value) && ($key === 'left' || $key === 'right' || $key === 'start' || $key === 'end' || $key === 'condition' || $key === 'range')) {
            $child_x = $x + 15; $child_y = $y - 8 - ($child_index * 8); $child_z = $z + ($child_index * 5);
            createSwiftASTNodes($value, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id); $child_index++;
        }
    }
    return $current_id;
}

function getSwiftASTNodeColor($type) {
    $colors = ['SourceFile' => '#3498db', 'ImportDeclaration' => '#2ecc71', 'FunctionDeclaration' => '#e74c3c', 'VariableDeclaration' => '#f39c12', 'FunctionCall' => '#9b59b6', 'BinaryExpression' => '#1abc9c', 'StringLiteral' => '#e67e22', 'IntegerLiteral' => '#8e44ad', 'Identifier' => '#16a085', 'ForLoop' => '#d35400', 'RangeExpression' => '#27ae60', 'StringInterpolation' => '#2980b9', 'IfStatement' => '#c0392b'];
    return $colors[$type] ?? '#95a5a6';
}

// If we reach here and no API endpoint was called, output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>3D Swift Compiler Visualizer - Final Project</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0a192f 0%, #112240 100%); color: #ccd6f6; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .header { background: rgba(17, 34, 64, 0.95); padding: 12px 15px; border-bottom: 1px solid rgba(100, 255, 218, 0.1); display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(10px); flex-wrap: wrap; gap: 10px; flex-shrink: 0; }
        .header h1 { color: #64ffda; font-size: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 8px; flex: 1; min-width: 250px; }
        .subtitle { color: #8892b0; font-size: 0.85rem; font-weight: 400; }
        .user-info { display: flex; align-items: center; gap: 12px; background: rgba(10,25,47,0.6); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(100,255,218,0.2); }
        .user-info a { color: #64ffda; text-decoration: none; font-size: 0.9rem; transition: color 0.3s; }
        .user-info a:hover { color: #ffffff; }
        .user-info span { color: #ccd6f6; font-size: 0.9rem; }
        .container { display: flex; flex-direction: column; flex: 1; padding: 10px; overflow: hidden; height: calc(100vh - 140px); }
        .main-content { display: flex; flex: 1; gap: 10px; overflow: hidden; height: 100%; }
        @media (max-width: 992px) { .main-content { flex-direction: column; } }
        .panel { background: rgba(17, 34, 64, 0.7); border-radius: 10px; padding: 15px; border: 1px solid rgba(100, 255, 218, 0.1); backdrop-filter: blur(5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); overflow: hidden; display: flex; flex-direction: column; }
        .controls-panel { width: 400px; min-width: 400px; display: flex; flex-direction: column; }
        @media (max-width: 992px) { .controls-panel { width: 100%; min-width: 100%; height: 400px; } }
        .visualization-panel { flex: 1; min-width: 0; }
        .output-panel { width: 450px; min-width: 450px; display: flex; flex-direction: column; }
        @media (max-width: 992px) { .output-panel { width: 100%; min-width: 100%; height: 500px; } }
        .panel-header { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid rgba(100, 255, 218, 0.3); display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .panel-header h2 { color: #64ffda; font-size: 1.2rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .panel-header i { color: #64ffda; font-size: 1.1rem; }
        .controls-scroll-container { flex: 1; overflow-y: auto; overflow-x: hidden; padding-right: 5px; min-height: 0; }
        .control-group { margin-bottom: 15px; }
        .control-group label { display: block; margin-bottom: 6px; color: #64ffda; font-weight: 500; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
        select, input[type="range"] { width: 100%; padding: 8px 10px; background: rgba(10, 25, 47, 0.8); border: 1px solid rgba(100, 255, 218, 0.2); border-radius: 6px; color: #e6f1ff; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; transition: all 0.3s; }
        select:focus, input[type="range"]:focus { outline: none; border-color: #64ffda; box-shadow: 0 0 0 2px rgba(100, 255, 218, 0.1); }
        select option { background: #112240; color: #e6f1ff; }
        .checkbox-group { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
        .checkbox-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #8892b0; font-weight: 400; transition: color 0.3s; }
        .checkbox-group label:hover { color: #e6f1ff; }
        .button-group { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        .btn { flex: 1; padding: 10px; border: none; border-radius: 6px; background: linear-gradient(135deg, #64ffda, #00d9a6); color: #0a192f; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px; min-width: 120px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(100, 255, 218, 0.4); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn.secondary { background: linear-gradient(135deg, #8892b0, #a8b2d1); color: #0a192f; }
        .btn.danger { background: linear-gradient(135deg, #ff6b6b, #ff4757); color: white; }
        .visualization-container { flex: 1; position: relative; overflow: hidden; min-height: 0; }
        #visualization-canvas { width: 100%; height: 100%; border-radius: 6px; }
        .visualization-controls { position: absolute; bottom: 15px; right: 15px; display: flex; gap: 8px; z-index: 10; }
        .icon-btn { width: 38px; height: 38px; border-radius: 50%; background: rgba(10, 25, 47, 0.8); border: 1px solid rgba(100, 255, 218, 0.3); color: #64ffda; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; font-size: 1rem; }
        .icon-btn:hover { background: rgba(100, 255, 218, 0.1); transform: scale(1.1); }
        .output-scroll-container { flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0; }
        .status-bar { padding: 12px; background: rgba(10, 25, 47, 0.8); border-radius: 6px; border: 1px solid rgba(100, 255, 218, 0.1); margin-top: 15px; flex-shrink: 0; }
        #status-message { margin-bottom: 8px; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
        .progress-bar { height: 5px; background: rgba(136, 146, 176, 0.2); border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #64ffda, #00d9a6); width: 0%; transition: width 0.5s; }
        .error-indicator { background: linear-gradient(90deg, #ff6b6b, #ff4757); }
        .code-editor { display: flex; flex-direction: column; margin-top: 15px; flex-shrink: 0; }
        .code-editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .code-editor-header h3 { color: #64ffda; font-size: 1rem; display: flex; align-items: center; gap: 6px; }
        textarea { width: 100%; min-height: 200px; background: #0a192f; color: #e6f1ff; border: 1px solid rgba(100, 255, 218, 0.2); border-radius: 6px; padding: 12px; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.5; resize: vertical; white-space: pre; overflow: auto; tab-size: 4; transition: border 0.3s; }
        textarea:focus { outline: none; border-color: #64ffda; }
        .output-tabs { display: flex; margin-bottom: 10px; border-bottom: 1px solid rgba(100, 255, 218, 0.1); flex-wrap: wrap; gap: 4px; flex-shrink: 0; }
        .output-tab { padding: 8px 12px; background: none; border: none; color: #8892b0; cursor: pointer; transition: all 0.3s; border-bottom: 2px solid transparent; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .output-tab:hover { color: #e6f1ff; }
        .output-tab.active { color: #64ffda; border-bottom-color: #64ffda; }
        .output-tab.error { color: #ff6b6b; }
        .output-display { background: #0a192f; border-radius: 6px; padding: 12px; font-family: 'JetBrains Mono', monospace; font-size: 11px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; overflow-y: auto; display: none; border: 1px solid rgba(100, 255, 218, 0.1); min-height: 200px; max-height: 300px; }
        .output-display.active { display: block; }
        .stage-info { margin-top: 15px; padding: 15px; background: rgba(10, 25, 47, 0.8); border-radius: 6px; font-size: 0.85rem; line-height: 1.5; border: 1px solid rgba(100, 255, 218, 0.1); flex-shrink: 0; }
        .error-section { margin-top: 15px; padding: 15px; background: rgba(255, 107, 107, 0.1); border-radius: 6px; border: 1px solid rgba(255, 107, 107, 0.3); max-height: 200px; overflow-y: auto; display: none; flex-shrink: 0; }
        .error-item { padding: 10px; margin-bottom: 8px; background: rgba(255, 107, 107, 0.2); border-radius: 5px; border-left: 4px solid #ff6b6b; font-size: 0.85rem; }
        .error-warning { border-left-color: #ffa502; }
        .stage-status { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: bold; margin-left: 8px; }
        .status-completed { background: rgba(100, 255, 218, 0.2); color: #64ffda; }
        .status-failed { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .status-pending { background: rgba(136, 146, 176, 0.2); color: #8892b0; }
        .tooltip { position: absolute; background: rgba(10, 25, 47, 0.95); color: #e6f1ff; padding: 10px; border-radius: 5px; border: 1px solid #64ffda; pointer-events: none; z-index: 1000; max-width: 250px; font-size: 0.8rem; display: none; backdrop-filter: blur(10px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5); }
        .footer { background: rgba(17, 34, 64, 0.95); padding: 12px 15px; border-top: 1px solid rgba(100, 255, 218, 0.1); backdrop-filter: blur(10px); flex-shrink: 0; }
        .footer-content { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; max-width: 1400px; margin: 0 auto; }
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
        @media (max-width: 992px) { .container { height: auto; min-height: calc(100vh - 140px); } .main-content { flex-direction: column; height: auto; } .controls-panel, .output-panel { height: auto; max-height: 500px; } .header h1 { font-size: 1.3rem; } .header { padding: 10px; } .footer-content { flex-direction: column; text-align: center; gap: 10px; } .authors { flex-direction: column; gap: 8px; } .course-info { order: -1; width: 100%; } .github-link { order: 2; } .visualization-panel { height: 400px; } }
        @media (max-width: 768px) { .header h1 { font-size: 1.2rem; } .subtitle { font-size: 0.8rem; } .panel { padding: 12px; } .panel-header h2 { font-size: 1.1rem; } .btn { padding: 8px; font-size: 0.85rem; min-width: 100px; } .output-tab { padding: 6px 10px; font-size: 0.8rem; } .footer { padding: 10px; } .author, .course-info, .github-link { font-size: 0.8rem; } }
        @media (max-width: 480px) { .header h1 { font-size: 1.1rem; min-width: auto; } .subtitle { font-size: 0.75rem; } .container { padding: 8px; } .panel { padding: 10px; } .button-group { flex-direction: column; } .btn { width: 100%; min-width: auto; } .output-tabs { justify-content: center; } .output-tab { padding: 5px 8px; font-size: 0.75rem; } .visualization-panel { height: 350px; } .code-editor-header { flex-direction: column; align-items: flex-start; } #example-select { width: 100%; margin-top: 5px; } }
        .mobile-menu-toggle { display: none; background: rgba(100, 255, 218, 0.1); border: 1px solid rgba(100, 255, 218, 0.3); color: #64ffda; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 0.9rem; margin-bottom: 10px; justify-content: center; align-items: center; gap: 8px; width: 100%; }
        @media (max-width: 992px) { .mobile-menu-toggle { display: flex; } .output-panel { max-height: 500px; transition: max-height 0.3s ease; } .output-panel.collapsed { max-height: 50px; overflow: hidden; } }
        .info-badge { display: inline-block; padding: 3px 6px; background: rgba(100, 255, 218, 0.1); border-radius: 4px; font-size: 0.75rem; color: #64ffda; margin-left: 8px; }
        textarea::-webkit-scrollbar { width: 8px; }
        textarea::-webkit-scrollbar-track { background: rgba(10, 25, 47, 0.5); }
        textarea::-webkit-scrollbar-thumb { background: rgba(100, 255, 218, 0.3); border-radius: 4px; }
        .export-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .controls-panel { height: 100%; }
        #example-select { padding: 6px 10px; background: rgba(10, 25, 47, 0.8); color: #e6f1ff; border-radius: 5px; border: 1px solid rgba(100, 255, 218, 0.2); font-size: 0.85rem; }
        .controls-scroll-container > * { flex-shrink: 0; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1><i class="fab fa-swift"></i> 3D Swift Compiler Visualizer</h1>
            <div class="subtitle">Interactive Visualization of Swift Compilation Pipeline & Memory Model</div>
        </div>
        <div class="course-info">
            <span class="university">COMPUTER SCIENCE FINAL PROJECT - SWIFT VERSION</span>
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
                    <h2>Swift Compilation Controls</h2>
                </div>
                <div class="controls-scroll-container">
                    <div class="control-group">
                        <label for="view-mode"><i class="fas fa-eye"></i> View Mode:</label>
                        <select id="view-mode">
                            <option value="pipeline">Pipeline View</option>
                            <option value="ast">AST View</option>
                            <option value="cfg">Control Flow Graph</option>
                            <option value="memory">Memory Model</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="stage-select"><i class="fas fa-code-branch"></i> Pipeline Stage:</label>
                        <select id="stage-select">
                            <option value="all">All Stages</option>
                            <option value="lexical">Lexical Analysis</option>
                            <option value="syntax">Syntax Analysis</option>
                            <option value="semantic">Semantic Analysis</option>
                            <option value="sil">SIL Generation</option>
                            <option value="optimization">Optimization</option>
                            <option value="llvm">LLVM IR Generation</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <label><input type="checkbox" id="auto-rotate" checked> <i class="fas fa-sync-alt"></i> Auto Rotate</label>
                        <label><input type="checkbox" id="show-labels" checked> <i class="fas fa-tag"></i> Show Labels</label>
                        <label><input type="checkbox" id="show-errors" checked> <i class="fas fa-exclamation-triangle"></i> Highlight Errors</label>
                    </div>
                    <div class="button-group">
                        <button id="reset-view" class="btn"><i class="fas fa-undo"></i> Reset View</button>
                        <button id="screenshot" class="btn secondary"><i class="fas fa-camera"></i> Screenshot</button>
                    </div>
                    <div class="panel-header" style="margin-top: 20px;">
                        <i class="fas fa-code"></i>
                        <h2>Swift Source Code Editor</h2>
                    </div>
                    <div class="code-editor">
                        <div class="code-editor-header">
                            <h3><i class="fab fa-swift"></i> Swift Source Code</h3>
                            <select id="example-select">
                                <option value="">Load Example...</option>
                                <option value="simple">Simple Swift Program</option>
                                <option value="conditional">Conditional Logic</option>
                                <option value="loop">Loop Example</option>
                                <option value="struct">Struct Example</option>
                            </select>
                        </div>
                        <textarea id="source-code" spellcheck="false">import Foundation

func main() {
    let x = 10
    let y = 20
    let result = x + y
    
    print("Result: \(result)")
    
    for i in 0..<5 {
        print("Iteration \(i)")
    }
    
    if result > 25 {
        print("Result is greater than 25")
    } else {
        print("Result is 25 or less")
    }
}

// Execute main function
main()</textarea>
                    </div>
                    <div class="button-group" style="margin-top: 15px;">
                        <button id="compile-btn" class="btn"><i class="fas fa-play"></i> Compile & Visualize</button>
                        <button id="reset-btn" class="btn danger"><i class="fas fa-trash"></i> Reset</button>
                    </div>
                    <div class="status-bar">
                        <div id="status-message"><i class="fas fa-info-circle"></i> Ready to compile Swift code</div>
                        <div class="progress-bar"><div id="progress-bar" class="progress-fill"></div></div>
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
                        <button id="zoom-in" class="icon-btn" title="Zoom In"><i class="fas fa-search-plus"></i></button>
                        <button id="zoom-out" class="icon-btn" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
                        <button id="reset-camera" class="icon-btn" title="Reset Camera"><i class="fas fa-crosshairs"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Output -->
            <div class="panel output-panel" id="output-panel">
                <button class="mobile-menu-toggle" id="output-toggle"><i class="fas fa-chevron-down"></i> <span>Output Panel</span></button>
                <div class="panel-header">
                    <i class="fas fa-terminal"></i>
                    <h2>Swift Compilation Output</h2>
                </div>
                <div class="output-scroll-container">
                    <div class="output-tabs">
                        <button class="output-tab active" data-output="tokens"><i class="fas fa-key"></i> <span class="tab-text">Tokens</span></button>
                        <button class="output-tab" data-output="ast"><i class="fas fa-project-diagram"></i> <span class="tab-text">AST</span></button>
                        <button class="output-tab" data-output="sil"><i class="fas fa-microchip"></i> <span class="tab-text">SIL Code</span></button>
                        <button class="output-tab" data-output="llvm"><i class="fas fa-file-code"></i> <span class="tab-text">LLVM IR</span></button>
                        <button class="output-tab" data-output="errors" id="errors-tab" style="display: none;"><i class="fas fa-exclamation-circle"></i> <span class="tab-text">Errors</span></button>
                    </div>
                    <div id="tokens-output" class="output-display active"></div>
                    <div id="ast-output" class="output-display"></div>
                    <div id="sil-output" class="output-display"></div>
                    <div id="llvm-output" class="output-display"></div>
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
                        <button id="download-ast" class="btn secondary"><i class="fas fa-download"></i> <span class="btn-text">AST (JSON)</span></button>
                        <button id="download-llvm" class="btn secondary"><i class="fas fa-download"></i> <span class="btn-text">LLVM IR</span></button>
                        <button id="download-source" class="btn secondary"><i class="fas fa-download"></i> <span class="btn-text">Source Code</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <div class="footer-content">
            <div class="course-info">
                <span>Final Year Project - Computer Science Department | Swift Compiler Design & Memory Model 3D Visualization System</span>
            </div>
            <div class="authors">
                <div class="author"><i class="fas fa-user-graduate"></i> <span>AGABA OLIVIER</span></div>
                <div class="author"><i class="fas fa-user-graduate"></i> <span>IRADI ARINDA</span></div>
            </div>
            <a href="https://github.com/Agabaofficial/swift-compiler-3d-visualizer" target="_blank" class="github-link"><i class="fab fa-github"></i> View on GitHub</a>
        </div>
    </div>
    
    <div id="tooltip" class="tooltip"></div>

    <script>
        class SwiftCompilerVisualizer3D {
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
                document.getElementById('download-llvm').addEventListener('click', () => this.download('llvm'));
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
                if (!sourceCode.trim()) { this.showStatus('Please enter Swift source code', 'error'); return; }
                this.showStatus('Compiling Swift code...', 'info');
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
                    this.showStatus(data.success ? 'Swift compilation successful!' : 'Compilation completed with warnings', data.success ? 'success' : 'warning');
                } catch (error) { this.showStatus('Error: ' + error.message, 'error'); this.updateProgress(0); console.error(error); }
            }
            
            updateOutputs(data) {
                if (data.outputs && data.outputs.tokens) { const tokensText = data.outputs.tokens.map(t => `Line ${t.line}: ${t.type.padEnd(15)} = "${t.value}"`).join('\n'); document.getElementById('tokens-output').textContent = tokensText; }
                if (data.outputs && data.outputs.ast) { document.getElementById('ast-output').textContent = JSON.stringify(data.outputs.ast, null, 2); }
                if (data.outputs && data.outputs.sil) { const silText = data.outputs.sil.map((instr, idx) => { const parts = []; for (const [key, value] of Object.entries(instr)) if (key !== 'op') parts.push(`${key}: ${value}`); return `${idx.toString().padStart(3)}: ${instr.op.padEnd(15)}` + (parts.length ? ' [' + parts.join(', ') + ']' : ''); }).join('\n'); document.getElementById('sil-output').textContent = silText; }
                if (data.outputs && data.outputs.llvm_ir) { document.getElementById('llvm-output').textContent = Array.isArray(data.outputs.llvm_ir) ? data.outputs.llvm_ir.join('\n') : data.outputs.llvm_ir; }
                if (data.errors && data.errors.length > 0) {
                    const errorsText = data.errors.map((err, idx) => `${idx+1}. ${err.type || 'Error'}:\n   ${err.message}\n   Line: ${err.line || 'N/A'}\n`).join('\n');
                    document.getElementById('errors-output').textContent = errorsText;
                    let errorHtml = '';
                    data.errors.forEach((error) => { const errorClass = error.severity === 'warning' ? 'error-warning' : ''; const icon = error.severity === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'; errorHtml += `<div class="error-item ${errorClass}"><div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;"><i class="fas ${icon}"></i> <strong>${error.type || 'Error'}:</strong> ${error.message}</div>${error.line ? `<div style="font-size:0.85rem; color:#ffd166;">Line ${error.line}</div>` : ''}</div>`; });
                    document.getElementById('error-list').innerHTML = errorHtml;
                    document.getElementById('error-section').style.display = 'block';
                } else { document.getElementById('error-section').style.display = 'none'; }
                if (data.stages && data.stages.length > 0) {
                    let stageHtml = '<h3 style="color:#64ffda; margin-bottom:12px; font-size:1rem;">Swift Compilation Pipeline</h3>';
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
            reset() { this.sessionId=null; this.totalErrors=0; document.getElementById('source-code').value=`import Foundation\n\nfunc main() {\n    let x = 10\n    let y = 20\n    let result = x + y\n    \n    print("Result: \\(result)")\n    \n    for i in 0..<5 {\n        print("Iteration \\(i)")\n    }\n    \n    if result > 25 {\n        print("Result is greater than 25")\n    } else {\n        print("Result is 25 or less")\n    }\n}\n\nmain()`; document.getElementById('tokens-output').textContent=''; document.getElementById('ast-output').textContent=''; document.getElementById('sil-output').textContent=''; document.getElementById('llvm-output').textContent=''; document.getElementById('errors-output').textContent=''; this.showOutput('tokens'); document.getElementById('errors-tab').style.display='none'; document.getElementById('error-section').style.display='none'; document.getElementById('stage-info').innerHTML=`<p><i class="fas fa-mouse-pointer"></i> Select a compilation stage or node to view details</p><div style="margin-top:12px; padding:12px; background:rgba(100,255,218,0.05); border-radius:5px; border:1px dashed rgba(100,255,218,0.2);"><p style="color:#64ffda; margin-bottom:6px; font-size:0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Tip:</strong></p><p style="font-size:0.8rem; color:#8892b0;">Hover over nodes in the 3D visualization to see detailed information. Click and drag to rotate the view.</p></div>`; this.clearScene(); this.createDefaultVisualization(); this.updateProgress(0); this.showStatus('Ready to compile Swift code'); }
            clearScene() { this.objects.forEach(obj=>this.scene.remove(obj)); this.objects=[]; this.labels.forEach(label=>this.scene.remove(label)); this.labels=[]; }
            resetView() { this.camera.position.set(0,30,50); this.controls.reset(); }
            zoom(factor) { this.camera.position.multiplyScalar(factor); this.controls.update(); }
            takeScreenshot() { this.renderer.render(this.scene,this.camera); const link=document.createElement('a'); link.href=this.renderer.domElement.toDataURL('image/png'); link.download=`swift_compiler_visualization_${Date.now()}.png`; link.click(); }
            async download(type) { if(!this.sessionId){ this.showStatus('No compilation session found','error'); return; } this.showStatus(`Downloading ${type}...`,'info'); try{ const response=await fetch(`?api=download&session_id=${this.sessionId}&type=${type}`); if(!response.ok) throw new Error('Download failed'); const blob=await response.blob(); const url=window.URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download=type==='llvm'?`llvm_${this.sessionId}.ll`:type==='ast'?`ast_${this.sessionId}.json`:`main_${this.sessionId}.swift`; document.body.appendChild(a); a.click(); document.body.removeChild(a); window.URL.revokeObjectURL(url); this.showStatus(`${type} downloaded successfully`,'success'); } catch(error){ this.showStatus('Download failed: '+error.message,'error'); } }
            loadExample(exampleId) { const examples={ simple:`import Foundation\n\nfunc main() {\n    let x = 10\n    let y = 20\n    let result = x + y\n    print("Result: \\(result)")\n}\n\nmain()`, conditional:`import Foundation\n\nfunc main() {\n    let score = 85\n    \n    if score >= 90 {\n        print("Grade: A")\n    } else if score >= 80 {\n        print("Grade: B")\n    } else if score >= 70 {\n        print("Grade: C")\n    } else {\n        print("Grade: F")\n    }\n}\n\nmain()`, loop:`import Foundation\n\nfunc main() {\n    let numbers = [1,2,3,4,5]\n    var sum = 0\n    \n    for number in numbers {\n        sum += number\n        print("Adding \\(number), sum is now \\(sum)")\n    }\n    \n    print("Total sum: \\(sum)")\n}\n\nmain()`, struct:`import Foundation\n\nstruct Person {\n    var name: String\n    var age: Int\n    \n    func introduce() {\n        print("Hello, my name is \\(name) and I'm \\(age) years old.")\n    }\n}\n\nfunc main() {\n    let person = Person(name: "Alice", age: 30)\n    person.introduce()\n}\n\nmain()` }; if(examples[exampleId]){ document.getElementById('source-code').value=examples[exampleId]; this.showStatus(`Loaded Swift example: ${exampleId}`,'success'); } document.getElementById('example-select').value=''; }
            onMouseMove(event) { const rect=this.renderer.domElement.getBoundingClientRect(); this.mouse.x=((event.clientX-rect.left)/rect.width)*2-1; this.mouse.y=-((event.clientY-rect.top)/rect.height)*2+1; this.raycaster.setFromCamera(this.mouse,this.camera); const intersects=this.raycaster.intersectObjects(this.objects); const tooltip=document.getElementById('tooltip'); if(intersects.length>0){ const data=intersects[0].object.userData; if(data){ tooltip.style.display='block'; tooltip.style.left=(event.clientX+10)+'px'; tooltip.style.top=(event.clientY+10)+'px'; let html=`<div style="color:#64ffda; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">${this.truncateText(data.name,25)}</div>`; html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Type:</strong> ${data.type}</div>`; if(data.status) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Status:</strong> ${data.status}</div>`; if(data.duration) html+=`<div style="margin-bottom:4px; font-size:0.8rem;"><strong>Duration:</strong> ${data.duration}ms</div>`; if(data.error_count>0) html+=`<div style="color:#ff6b6b; margin-top:6px; font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> ${data.error_count} error(s)</div>`; tooltip.innerHTML=html; } } else { tooltip.style.display='none'; } }
            handleResize() { const container=document.getElementById('visualization-canvas'); this.camera.aspect=container.clientWidth/container.clientHeight; this.camera.updateProjectionMatrix(); this.renderer.setSize(container.clientWidth,container.clientHeight); this.isMobile=window.innerWidth<992; }
            animate() { this.animationId=requestAnimationFrame(()=>this.animate()); if(this.autoRotate) this.scene.rotation.y+=0.001; this.controls.update(); this.renderer.render(this.scene,this.camera); }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            window.swiftVisualizer = new SwiftCompilerVisualizer3D();
            if (window.innerWidth < 768) {
                document.querySelectorAll('.tab-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
                document.querySelectorAll('.btn-text').forEach(el => { if (window.innerWidth < 480) el.style.display = 'none'; });
            }
            if (window.innerWidth < 768) { const textarea = document.getElementById('source-code'); textarea.style.minHeight = '150px'; }
        });
    </script>
</body>
</html>