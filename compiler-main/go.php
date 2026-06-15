<?php
// go.php - Go Compiler Visualizer with Authentication & Logging

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Turn off error display but log errors
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'go_errors.log');

// Create tmp directory if it doesn't exist
if (!is_dir('tmp')) {
    mkdir('tmp', 0777, true);
}

// Handle API calls - check before any output
$api_endpoint = $_GET['api'] ?? '';
if ($api_endpoint) {
    handleApiRequest($api_endpoint);
    exit;
}

function handleApiRequest($endpoint) {
    // Clear any existing output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Start fresh output buffer
    ob_start();
    
    try {
        switch ($endpoint) {
            case 'compile':
                $result = handleGoCompile();
                break;
            case 'visualize':
                $result = handleVisualize();
                break;
            case 'step':
                $result = handleStep();
                break;
            case 'download':
                handleDownload();
                exit;
            case 'errors':
                $result = handleErrorReport();
                break;
            default:
                $result = ['error' => 'Invalid API endpoint'];
                break;
        }
        
        // Ensure we only output JSON
        ob_clean();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Throwable $t) {
        ob_clean();
        echo json_encode(['error' => $t->getMessage()]);
    }
    
    ob_end_flush();
}

function handleGoCompile() {
    // Get JSON input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return ['error' => 'No input data received'];
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON input: ' . json_last_error_msg()];
    }
    
    $source_code = $data['source_code'] ?? '';
    
    if (empty($source_code)) {
        return ['error' => 'No source code provided'];
    }
    
    $session_id = uniqid('go_compile_', true);
    $tmp_dir = "tmp/{$session_id}";
    
    if (!mkdir($tmp_dir, 0777, true) && !is_dir($tmp_dir)) {
        return ['error' => 'Failed to create temporary directory'];
    }
    
    // Save the source code
    if (file_put_contents("{$tmp_dir}/main.go", $source_code) === false) {
        return ['error' => 'Failed to save source code'];
    }
    
    $result = [
        'session_id' => $session_id,
        'success' => true,
        'stages' => [],
        'outputs' => [],
        'errors' => [],
        'source_code' => $source_code
    ];
    
    // Generate dynamic compilation results based on input
    $lexicalResult = simulateGoTokens($source_code);
    $result['stages'][] = [
        'name' => 'Lexical Analysis',
        'status' => $lexicalResult['has_errors'] ? 'failed' : 'completed',
        'duration' => rand(50, 200),
        'tokens' => $lexicalResult['tokens'],
        'errors' => $lexicalResult['errors']
    ];
    
    $syntaxResult = simulateGoAST($source_code);
    $result['stages'][] = [
        'name' => 'Syntax Analysis',
        'status' => $syntaxResult['has_errors'] ? 'failed' : 'completed',
        'duration' => rand(100, 300),
        'ast' => $syntaxResult['ast'],
        'errors' => $syntaxResult['errors']
    ];
    
    $semanticResult = simulateGoSymbolTable($source_code);
    $result['stages'][] = [
        'name' => 'Semantic Analysis',
        'status' => $semanticResult['has_errors'] ? 'failed' : 'completed',
        'duration' => rand(80, 250),
        'symbol_table' => $semanticResult['symbol_table'],
        'errors' => $semanticResult['errors']
    ];
    
    $irResult = simulateGoSSA($source_code);
    $result['stages'][] = [
        'name' => 'SSA Generation',
        'status' => $irResult['has_errors'] ? 'failed' : 'completed',
        'duration' => rand(150, 400),
        'ssa_code' => $irResult['ssa'],
        'errors' => $irResult['errors']
    ];
    
    $optResult = simulateGoOptimization($source_code);
    $result['stages'][] = [
        'name' => 'Optimization',
        'status' => $optResult['has_errors'] ? 'failed' : 'completed',
        'duration' => rand(200, 500),
        'optimizations' => $optResult['optimizations'],
        'errors' => $optResult['errors']
    ];
    
    $machineCodeResult = simulateGoAssembly($source_code);
    $result['stages'][] = [
        'name' => 'Assembly Generation',
        'status' => $machineCodeResult['has_errors'] ? 'failed' : 'completed',
        'duration' => rand(250, 600),
        'assembly' => $machineCodeResult['assembly'],
        'errors' => $machineCodeResult['errors']
    ];
    
    // Collect all errors
    $all_errors = [];
    foreach ($result['stages'] as $stage) {
        if (!empty($stage['errors'])) {
            $all_errors = array_merge($all_errors, $stage['errors']);
            $result['success'] = false;
        }
    }
    
    $result['errors'] = $all_errors;
    
    $result['outputs'] = [
        'tokens' => $result['stages'][0]['tokens'],
        'ast' => $result['stages'][1]['ast'],
        'ssa' => $result['stages'][3]['ssa_code'],
        'assembly' => $result['stages'][5]['assembly']
    ];
    
    // Save result
    file_put_contents("{$tmp_dir}/result.json", json_encode($result, JSON_PRETTY_PRINT));
    
    // ----- LOGGING -----
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $language = 'go';
    $success = $result['success'];
    $errorsCount = count($result['errors']);
    $compilationTimeMs = array_sum(array_column($result['stages'], 'duration'));
    logCompilation($userId, $language, $session_id, $source_code, $success, $errorsCount, $compilationTimeMs);
    if ($userId) {
        logActivity($userId, 'compile', "Compiled Go code, session: $session_id, errors: $errorsCount");
    }
    // ----- END LOGGING -----
    
    return $result;
}

function handleVisualize() {
    $session_id = $_GET['session_id'] ?? '';
    $stage = $_GET['stage'] ?? 'all';
    $view = $_GET['view'] ?? 'pipeline';
    
    if (empty($session_id)) {
        return ['error' => 'No session ID provided'];
    }
    
    $result_file = "tmp/{$session_id}/result.json";
    if (!file_exists($result_file)) {
        return ['error' => 'Session not found or compilation not completed'];
    }
    
    $result = json_decode(file_get_contents($result_file), true);
    
    // Generate visualization data based on stage and view
    $viz_data = generateGoVisualizationData($result, $stage, $view);
    
    return $viz_data;
}

function handleStep() {
    return [
        'message' => 'Step execution not implemented in this demo',
        'current_stage' => 'lexical',
        'next_stage' => 'syntax'
    ];
}

function handleDownload() {
    $session_id = $_GET['session_id'] ?? '';
    $type = $_GET['type'] ?? 'source';
    
    if (empty($session_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'No session ID provided']);
        exit;
    }
    
    // ----- LOG ACTIVITY -----
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    if ($userId) {
        logActivity($userId, 'download', "Downloaded $type from session: $session_id");
    }
    // ----- END LOGGING -----
    
    $tmp_dir = "tmp/{$session_id}";
    
    switch ($type) {
        case 'source':
            $file = "{$tmp_dir}/main.go";
            $filename = "main_{$session_id}.go";
            $content_type = 'text/plain';
            break;
        case 'ast':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) {
                http_response_code(404);
                echo json_encode(['error' => 'File not found']);
                exit;
            }
            
            $result = json_decode(file_get_contents($result_file), true);
            $content = json_encode($result['outputs']['ast'], JSON_PRETTY_PRINT);
            $filename = "ast_{$session_id}.json";
            $content_type = 'application/json';
            
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
            
        case 'assembly':
            $result_file = "{$tmp_dir}/result.json";
            if (!file_exists($result_file)) {
                http_response_code(404);
                echo json_encode(['error' => 'File not found']);
                exit;
            }
            
            $result = json_decode(file_get_contents($result_file), true);
            $assembly = $result['outputs']['assembly'] ?? [];
            $content = is_array($assembly) ? implode("\n", $assembly) : $assembly;
            $filename = "assembly_{$session_id}.asm";
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
    
    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file));
    
    readfile($file);
    exit;
}

function handleErrorReport() {
    $session_id = $_GET['session_id'] ?? '';
    
    if (empty($session_id)) {
        return ['error' => 'No session ID provided'];
    }
    
    $result_file = "tmp/{$session_id}/result.json";
    if (!file_exists($result_file)) {
        return ['error' => 'Session not found'];
    }
    
    $result = json_decode(file_get_contents($result_file), true);
    
    return [
        'session_id' => $session_id,
        'errors' => $result['errors'] ?? [],
        'warnings' => $result['warnings'] ?? [],
        'total_errors' => count($result['errors'] ?? []),
        'success' => $result['success'] ?? false
    ];
}

// Go-specific functions
function simulateGoTokens($code) {
    $tokens = [];
    $errors = [];
    
    $code = preg_replace('/\/\/.*$/m', '', $code);
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    
    $lines = explode("\n", $code);
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Handle package and import statements
        if (strpos($line, 'package ') === 0 || strpos($line, 'import ') === 0) {
            $tokens[] = [
                'type' => 'KEYWORD',
                'value' => strpos($line, 'package ') === 0 ? 'package' : 'import',
                'line' => $lineNum + 1
            ];
            continue;
        }
        
        // Handle Go-specific syntax
        if (strpos($line, 'func ') === 0 || strpos($line, 'var ') === 0 || 
            strpos($line, 'const ') === 0 || strpos($line, 'type ') === 0) {
            $tokens[] = [
                'type' => 'KEYWORD',
                'value' => explode(' ', $line)[0],
                'line' => $lineNum + 1
            ];
        }
        
        $quoteCount = substr_count($line, '"') + substr_count($line, '`');
        $singleQuoteCount = substr_count($line, "'");
        
        if ($quoteCount % 2 != 0) {
            $errors[] = [
                'type' => 'lexical',
                'message' => 'Unterminated string literal',
                'line' => $lineNum + 1,
                'severity' => 'error'
            ];
        }
        
        if ($singleQuoteCount % 2 != 0) {
            $errors[] = [
                'type' => 'lexical',
                'message' => 'Unterminated rune literal',
                'line' => $lineNum + 1,
                'severity' => 'error'
            ];
        }
        
        // Handle Go-specific patterns
        $words = preg_split('/(\s+|(?<=[(){}\[\];=+\-\/*<>!,\.:])|(?=[(){}\[\];=+\-\/*<>!,\.:]))/', $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        
        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            
            $token = [
                'type' => 'IDENTIFIER',
                'value' => $word,
                'line' => $lineNum + 1
            ];
            
            $keywords = ['package', 'import', 'func', 'var', 'const', 'type', 'struct',
                        'interface', 'map', 'chan', 'go', 'select', 'defer', 'range',
                        'if', 'else', 'for', 'switch', 'case', 'default', 'break',
                        'continue', 'return', 'fallthrough', 'goto', 'nil', 'true', 'false',
                        'iota', 'int', 'int8', 'int16', 'int32', 'int64', 'uint',
                        'uint8', 'uint16', 'uint32', 'uint64', 'uintptr', 'float32',
                        'float64', 'complex64', 'complex128', 'byte', 'rune', 'string',
                        'bool', 'error', 'make', 'len', 'cap', 'append', 'copy', 'close',
                        'panic', 'recover', 'new', 'print', 'println'];
            
            if (in_array($word, $keywords)) {
                $token['type'] = 'KEYWORD';
            } elseif (is_numeric($word)) {
                $token['type'] = 'LITERAL';
            } elseif (preg_match('/^".*"$/', $word) || preg_match("/^`.*`$/", $word)) {
                $token['type'] = 'STRING_LITERAL';
            } elseif (preg_match("/^'.*'$/", $word)) {
                $token['type'] = 'RUNE_LITERAL';
            } elseif (preg_match('/^[+\-*/=<>!&|^%]+$/', $word)) {
                $token['type'] = 'OPERATOR';
            } elseif (preg_match('/^[(){}\[\];,\.:]+$/', $word)) {
                $token['type'] = 'PUNCTUATOR';
            } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $word)) {
                // Already set as IDENTIFIER
            } elseif ($word === ':=') {
                $token['type'] = 'OPERATOR';
                $token['value'] = ':=';
            }
            
            $tokens[] = $token;
        }
    }
    
    return [
        'tokens' => $tokens,
        'errors' => $errors,
        'has_errors' => !empty($errors)
    ];
}

function simulateGoAST($code) {
    $errors = [];
    
    $lines = explode("\n", $code);
    $brace_count = 0;
    $paren_count = 0;
    $bracket_count = 0;
    $in_func = false;
    $in_struct = false;
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, '//') === 0 || strpos($line, '/*') === 0) {
            continue;
        }
        
        // Count brackets for syntax checking
        $brace_count += substr_count($line, '{');
        $brace_count -= substr_count($line, '}');
        $paren_count += substr_count($line, '(');
        $paren_count -= substr_count($line, ')');
        $bracket_count += substr_count($line, '[');
        $bracket_count -= substr_count($line, ']');
        
        // Check for Go-specific syntax patterns
        if (preg_match('/func\s+\w+\s*\(/', $line)) {
            $in_func = true;
        }
        
        if (preg_match('/type\s+\w+\s+struct/', $line)) {
            $in_struct = true;
        }
        
        // Check for Go-specific issues
        if (strpos($line, ':=') !== false && !preg_match('/\w+\s*:=\s*\w+/', $line)) {
            $errors[] = [
                'type' => 'syntax',
                'message' => 'Invalid short variable declaration',
                'line' => $lineNum + 1,
                'severity' => 'error'
            ];
        }
    }
    
    if ($brace_count != 0) {
        $errors[] = [
            'type' => 'syntax',
            'message' => 'Unbalanced braces',
            'severity' => 'error'
        ];
    }
    
    if ($paren_count != 0) {
        $errors[] = [
            'type' => 'syntax',
            'message' => 'Unbalanced parentheses',
            'severity' => 'error'
        ];
    }
    
    if ($bracket_count != 0) {
        $errors[] = [
            'type' => 'syntax',
            'message' => 'Unbalanced brackets',
            'severity' => 'error'
        ];
    }
    
    // Generate Go-like AST
    $ast = [
        'type' => 'File',
        'package' => 'main',
        'imports' => ['fmt'],
        'declarations' => [
            [
                'type' => 'Function',
                'name' => 'main',
                'parameters' => [],
                'results' => [],
                'body' => [
                    [
                        'type' => 'VariableDeclaration',
                        'name' => 'x',
                        'type' => 'int',
                        'value' => ['type' => 'Literal', 'value' => 10]
                    ],
                    [
                        'type' => 'PrintStatement',
                        'function' => 'fmt.Println',
                        'args' => [
                            ['type' => 'Literal', 'value' => '"Hello, Go!"']
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // Enhance AST based on actual code
    if (strpos($code, 'fmt.Println') !== false) {
        $ast['declarations'][0]['body'][] = [
            'type' => 'PrintStatement',
            'function' => 'fmt.Println',
            'args' => [
                ['type' => 'Literal', 'value' => '"Compiled with Go Visualizer"']
            ]
        ];
    }
    
    if (strpos($code, 'for') !== false) {
        $ast['declarations'][0]['body'][] = [
            'type' => 'ForLoop',
            'initialization' => [
                'type' => 'VariableDeclaration',
                'name' => 'i',
                'type' => 'int',
                'value' => ['type' => 'Literal', 'value' => 0]
            ],
            'condition' => [
                'type' => 'BinaryExpression',
                'operator' => '<',
                'left' => ['type' => 'Identifier', 'name' => 'i'],
                'right' => ['type' => 'Literal', 'value' => 5]
            ],
            'increment' => [
                'type' => 'UnaryExpression',
                'operator' => '++',
                'operand' => ['type' => 'Identifier', 'name' => 'i']
            ],
            'body' => [
                [
                    'type' => 'PrintStatement',
                    'function' => 'fmt.Println',
                    'args' => [
                        ['type' => 'Literal', 'value' => '"Iteration"'],
                        ['type' => 'Identifier', 'name' => 'i']
                    ]
                ]
            ]
        ];
    }
    
    return [
        'ast' => $ast,
        'errors' => $errors,
        'has_errors' => !empty($errors)
    ];
}

function simulateGoSymbolTable($code) {
    $symbol_table = [
        'package' => null,
        'imports' => [],
        'functions' => [],
        'variables' => [],
        'types' => [],
        'constants' => []
    ];
    
    $errors = [];
    
    $lines = explode("\n", $code);
    $current_func = null;
    $declared_vars = [];
    $declared_funcs = [];
    $declared_types = [];
    
    $go_builtins = ['fmt', 'Println', 'Printf', 'Sprintf', 'len', 'cap', 'make',
                   'append', 'copy', 'close', 'panic', 'recover', 'complex',
                   'real', 'imag', 'new', 'delete'];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        
        $line_without_strings = preg_replace('/`[^`]*`/', 'STRING', $line);
        $line_without_strings = preg_replace('/"[^"]*"/', 'STRING', $line_without_strings);
        
        if (strpos($line, '//') === 0 || strpos($line, '/*') === 0) {
            continue;
        }
        
        // Detect package declaration
        if (preg_match('/^package\s+(\w+)/', $line, $matches)) {
            $symbol_table['package'] = $matches[1];
        }
        
        // Detect imports
        elseif (strpos($line, 'import') === 0) {
            if (preg_match('/import\s+"([^"]+)"/', $line, $matches)) {
                $symbol_table['imports'][] = $matches[1];
            } elseif (preg_match('/import\s+\(([^)]+)\)/s', $code, $matches)) {
                $imports = explode("\n", $matches[1]);
                foreach ($imports as $import) {
                    $import = trim($import);
                    if (preg_match('/"([^"]+)"/', $import, $imp_matches)) {
                        $symbol_table['imports'][] = $imp_matches[1];
                    }
                }
            }
        }
        
        // Detect function declaration
        elseif (preg_match('/^func\s+(\w+)\s*\(/', $line_without_strings, $matches)) {
            $func_name = $matches[1];
            $current_func = $func_name;
            $declared_funcs[] = $func_name;
            
            $symbol_table['functions'][$func_name] = [
                'name' => $func_name,
                'parameters' => [],
                'returns' => [],
                'line' => $lineNum + 1
            ];
        }
        
        // Detect variable declaration
        elseif (preg_match('/^var\s+(\w+)\s+(\w+)/', $line_without_strings, $matches)) {
            $var_name = $matches[1];
            $var_type = $matches[2];
            $declared_vars[] = $var_name;
            
            $symbol_table['variables'][] = [
                'name' => $var_name,
                'type' => $var_type,
                'scope' => $current_func ? $current_func : 'global',
                'line' => $lineNum + 1
            ];
        }
        
        // Detect short variable declaration
        elseif (preg_match('/(\w+)\s*:=\s*([^;]+)/', $line_without_strings, $matches)) {
            $var_name = $matches[1];
            $declared_vars[] = $var_name;
            
            $symbol_table['variables'][] = [
                'name' => $var_name,
                'type' => 'inferred',
                'scope' => $current_func ? $current_func : 'global',
                'line' => $lineNum + 1
            ];
        }
        
        // Detect type declaration
        elseif (preg_match('/^type\s+(\w+)\s+(\w+)/', $line_without_strings, $matches)) {
            $type_name = $matches[1];
            $type_kind = $matches[2];
            $declared_types[] = $type_name;
            
            $symbol_table['types'][$type_name] = [
                'name' => $type_name,
                'kind' => $type_kind,
                'line' => $lineNum + 1
            ];
        }
        
        // Check for undeclared identifiers
        if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $line_without_strings, $matches)) {
            foreach ($matches[1] as $identifier) {
                if (in_array($identifier, $go_builtins) ||
                    in_array($identifier, ['package', 'import', 'func', 'var', 'const', 'type',
                                          'struct', 'interface', 'if', 'else', 'for', 'switch',
                                          'case', 'default', 'return', 'break', 'continue',
                                          'fallthrough', 'goto', 'go', 'defer', 'range',
                                          'select', 'map', 'chan', 'nil', 'true', 'false']) ||
                    $identifier === 'STRING' ||
                    is_numeric($identifier)) {
                    continue;
                }
                
                if (!in_array($identifier, $declared_vars) && 
                    !in_array($identifier, $declared_funcs) &&
                    !in_array($identifier, $declared_types) &&
                    $identifier !== $symbol_table['package']) {
                    
                    // Check if it's from an import
                    $is_imported = false;
                    foreach ($symbol_table['imports'] as $import) {
                        $parts = explode('/', $import);
                        $pkg_name = end($parts);
                        if (strpos($identifier, $pkg_name . '.') === 0) {
                            $is_imported = true;
                            break;
                        }
                    }
                    
                    if (!$is_imported) {
                        $errors[] = [
                            'type' => 'semantic',
                            'message' => "Undeclared identifier: $identifier",
                            'line' => $lineNum + 1,
                            'severity' => 'warning'
                        ];
                    }
                }
            }
        }
    }
    
    return [
        'symbol_table' => $symbol_table,
        'errors' => $errors,
        'has_errors' => !empty($errors)
    ];
}

function simulateGoSSA($code) {
    $ssa = [];
    $errors = [];
    
    $lines = explode("\n", $code);
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '//') === 0 || strpos($line, '/*') === 0) {
            continue;
        }
        
        // Match variable declarations and assignments
        if (preg_match('/(\w+)\s*:=\s*(\d+)/', $line, $matches)) {
            $ssa[] = ['op' => 'alloc', 'dest' => "%$matches[1]", 'type' => 'int', 'value' => $matches[2]];
        } elseif (preg_match('/(\w+)\s*:=\s*(\w+)\s*\+\s*(\w+)/', $line, $matches)) {
            $ssa[] = ['op' => 'add', 'dest' => "%$matches[1]", 'src1' => "%$matches[2]", 'src2' => "%$matches[3]"];
        } elseif (preg_match('/fmt\.Println\("(.*)%d",\s*(\w+)\)/', $line, $matches)) {
            $ssa[] = ['op' => 'print', 'format' => $matches[1], 'arg' => "%$matches[2]"];
        } elseif (preg_match('/fmt\.Println\("(.*)"\)/', $line, $matches)) {
            $ssa[] = ['op' => 'print', 'format' => $matches[1]];
        } elseif (preg_match('/return\s+(\w+)/', $line, $matches)) {
            $ssa[] = ['op' => 'ret', 'value' => "%$matches[1]"];
        } elseif (preg_match('/for\s+(\w+)\s*:=\s*(\d+);.*<\s*(\d+);.*\+\+\)/', $line, $matches)) {
            $ssa[] = ['op' => 'phi', 'dest' => "%$matches[1]", 'initial' => $matches[2], 'loop' => "%$matches[1]"];
            $ssa[] = ['op' => 'loop', 'var' => "%$matches[1]", 'limit' => $matches[3]];
        }
    }
    
    return [
        'ssa' => $ssa,
        'errors' => $errors,
        'has_errors' => !empty($errors)
    ];
}

function simulateGoOptimization($code) {
    $optimizations = [];
    $errors = [];
    
    $optimizations[] = 'Dead code elimination';
    $optimizations[] = 'Constant propagation';
    $optimizations[] = 'Common subexpression elimination';
    $optimizations[] = 'Bounds check elimination';
    $optimizations[] = 'Escape analysis';
    $optimizations[] = 'Inlining';
    $optimizations[] = 'Nil check elimination';
    $optimizations[] = 'Stack allocation';
    
    // Check for optimization opportunities
    if (strpos($code, 'range') !== false) {
        $optimizations[] = 'Range loop optimization';
    }
    
    if (strpos($code, 'append') !== false) {
        $optimizations[] = 'Slice growth optimization';
    }
    
    if (strpos($code, 'interface{}') !== false) {
        $optimizations[] = 'Interface method dispatch optimization';
    }
    
    if (preg_match('/for\s*\{\s*}/', $code)) {
        $optimizations[] = 'Empty loop removal';
    }
    
    return [
        'optimizations' => $optimizations,
        'errors' => $errors,
        'has_errors' => !empty($errors)
    ];
}

function simulateGoAssembly($code) {
    $assembly = [];
    $errors = [];
    
    // Generate Go assembly based on code content
    $assembly[] = '; Generated Go Assembly (x86-64)';
    $assembly[] = '; Source: ' . substr($code, 0, 50) . '...';
    $assembly[] = '';
    
    $assembly[] = 'TEXT ·main(SB), $0-0';
    $assembly[] = '';
    
    // Add assembly based on code analysis
    if (preg_match('/(\w+)\s*:=\s*(\d+)/', $code, $matches)) {
        $assembly[] = '    ; ' . $matches[1] . ' := ' . $matches[2];
        $assembly[] = '    MOVQ $' . $matches[2] . ', AX';
        $assembly[] = '    MOVQ AX, ' . $matches[1] . '-8(SP)';
        $assembly[] = '';
    }
    
    if (preg_match('/(\w+)\s*:=\s*(\w+)\s*\+\s*(\w+)/', $code, $matches)) {
        $assembly[] = '    ; ' . $matches[1] . ' := ' . $matches[2] . ' + ' . $matches[3];
        $assembly[] = '    MOVQ ' . $matches[2] . '-8(SP), AX';
        $assembly[] = '    MOVQ ' . $matches[3] . '-8(SP), BX';
        $assembly[] = '    ADDQ BX, AX';
        $assembly[] = '    MOVQ AX, ' . $matches[1] . '-8(SP)';
        $assembly[] = '';
    }
    
    if (strpos($code, 'fmt.Println') !== false) {
        $assembly[] = '    ; Print statement';
        $assembly[] = '    LEAQ ·hellostr(SB), AX';
        $assembly[] = '    MOVQ AX, 0(SP)';
        $assembly[] = '    MOVQ $' . (strpos($code, '"Hello') !== false ? '13' : '8') . ', 8(SP)';
        $assembly[] = '    CALL runtime·printstring(SB)';
        $assembly[] = '';
    }
    
    if (preg_match('/for\s+(\w+)\s*:=\s*(\d+);.*<\s*(\d+);.*\+\+\)/', $code, $matches)) {
        $assembly[] = '    ; Loop initialization';
        $assembly[] = '    MOVQ $' . $matches[2] . ', CX';
        $assembly[] = '    MOVQ CX, ' . $matches[1] . '-8(SP)';
        $assembly[] = '';
        $assembly[] = 'loop:';
        $assembly[] = '    ; Loop condition';
        $assembly[] = '    CMPQ CX, $' . $matches[3];
        $assembly[] = '    JGE endloop';
        $assembly[] = '    ; Loop body';
        $assembly[] = '    ; ... loop operations ...';
        $assembly[] = '    ; Increment';
        $assembly[] = '    INCQ CX';
        $assembly[] = '    MOVQ CX, ' . $matches[1] . '-8(SP)';
        $assembly[] = '    JMP loop';
        $assembly[] = 'endloop:';
        $assembly[] = '';
    }
    
    $assembly[] = '    ; Return';
    $assembly[] = '    RET';
    $assembly[] = '';
    
    if (strpos($code, '"Hello') !== false) {
        $assembly[] = 'DATA ·hellostr(SB)/8, $"Hello, Go!"';
    } else {
        $assembly[] = 'DATA ·hellostr(SB)/8, $"Go Result"';
    }
    
    $assembly[] = 'GLOBL ·hellostr(SB), RODATA, $16';
    
    return [
        'assembly' => $assembly,
        'errors' => $errors,
        'has_errors' => !empty($errors)
    ];
}

function generateGoVisualizationData($result, $stage, $view) {
    $nodes = [];
    $edges = [];
    
    if ($view === 'pipeline') {
        $stages = [
            ['id' => 'lexical', 'name' => 'Lexical Analysis', 'x' => -30, 'y' => 0, 'z' => 0],
            ['id' => 'syntax', 'name' => 'Syntax Analysis', 'x' => -18, 'y' => 0, 'z' => 0],
            ['id' => 'semantic', 'name' => 'Semantic Analysis', 'x' => -6, 'y' => 0, 'z' => 0],
            ['id' => 'ssa', 'name' => 'SSA Generation', 'x' => 6, 'y' => 0, 'z' => 0],
            ['id' => 'optimization', 'name' => 'Optimization', 'x' => 18, 'y' => 0, 'z' => 0],
            ['id' => 'assembly', 'name' => 'Assembly Generation', 'x' => 30, 'y' => 0, 'z' => 0],
        ];
        
        foreach ($stages as $stage_info) {
            $stage_data = null;
            foreach ($result['stages'] as $s) {
                if (strpos(strtolower($s['name']), strtolower($stage_info['id'])) !== false) {
                    $stage_data = $s;
                    break;
                }
            }
            
            $has_errors = $stage_data && !empty($stage_data['errors']);
            
            $nodes[] = [
                'id' => $stage_info['id'],
                'name' => $stage_info['name'],
                'type' => 'stage',
                'color' => $has_errors ? '#e74c3c' : '#2ecc71',
                'position' => ['x' => $stage_info['x'], 'y' => $stage_info['y'], 'z' => $stage_info['z']],
                'size' => 3,
                'status' => $stage_data['status'] ?? 'pending',
                'duration' => $stage_data['duration'] ?? 0,
                'has_errors' => $has_errors,
                'error_count' => $has_errors ? count($stage_data['errors']) : 0
            ];
        }
        
        // Create edges between stages
        for ($i = 0; $i < count($stages) - 1; $i++) {
            $edges[] = [
                'from' => $stages[$i]['id'],
                'to' => $stages[$i+1]['id'],
                'type' => 'pipeline',
                'color' => '#3498db'
            ];
        }
    } elseif ($view === 'ast') {
        if (isset($result['outputs']['ast'])) {
            $ast = $result['outputs']['ast'];
            $node_id = 0;
            createGoASTNodes($ast, $nodes, $edges, $node_id, 0, 10, 0, -1);
        }
    } elseif ($view === 'cfg') {
        $cfg_nodes = [
            ['id' => 'entry', 'name' => 'Entry', 'x' => 0, 'y' => 20, 'z' => 0, 'type' => 'entry'],
            ['id' => 'decl', 'name' => 'Declarations', 'x' => -10, 'y' => 10, 'z' => 0, 'type' => 'declaration'],
            ['id' => 'init', 'name' => 'Initialization', 'x' => -10, 'y' => 0, 'z' => 0, 'type' => 'statement'],
            ['id' => 'cond', 'name' => 'Condition Check', 'x' => 0, 'y' => -10, 'z' => 0, 'type' => 'condition'],
            ['id' => 'body', 'name' => 'Function Body', 'x' => 10, 'y' => 0, 'z' => 0, 'type' => 'statement'],
            ['id' => 'inc', 'name' => 'Increment', 'x' => 10, 'y' => 10, 'z' => 0, 'type' => 'statement'],
            ['id' => 'exit', 'name' => 'Exit', 'x' => 0, 'y' => -20, 'z' => 0, 'type' => 'exit'],
        ];
        
        foreach ($cfg_nodes as $node) {
            $nodes[] = [
                'id' => $node['id'],
                'name' => $node['name'],
                'type' => $node['type'],
                'color' => getCFGNodeColor($node['type']),
                'position' => ['x' => $node['x'], 'y' => $node['y'], 'z' => $node['z']],
                'size' => 2.5,
                'has_errors' => false,
                'error_count' => 0
            ];
        }
        
        $cfg_edges = [
            ['from' => 'entry', 'to' => 'decl', 'type' => 'control_flow'],
            ['from' => 'decl', 'to' => 'init', 'type' => 'control_flow'],
            ['from' => 'init', 'to' => 'cond', 'type' => 'control_flow'],
            ['from' => 'cond', 'to' => 'body', 'type' => 'conditional', 'label' => 'true'],
            ['from' => 'cond', 'to' => 'exit', 'type' => 'conditional', 'label' => 'false'],
            ['from' => 'body', 'to' => 'inc', 'type' => 'control_flow'],
            ['from' => 'inc', 'to' => 'cond', 'type' => 'control_flow'],
        ];
        
        foreach ($cfg_edges as $edge) {
            $edges[] = $edge;
        }
    } elseif ($view === 'goroutine') {
        $goroutine_sections = [
            ['id' => 'heap', 'name' => 'Heap', 'x' => -20, 'y' => 15, 'z' => 0, 'size' => 4, 'height' => 5],
            ['id' => 'stack_main', 'name' => 'Main Stack', 'x' => -10, 'y' => 15, 'z' => 0, 'size' => 3, 'height' => 4],
            ['id' => 'stack_g1', 'name' => 'Goroutine 1', 'x' => 0, 'y' => 15, 'z' => 0, 'size' => 2, 'height' => 3],
            ['id' => 'stack_g2', 'name' => 'Goroutine 2', 'x' => 10, 'y' => 15, 'z' => 0, 'size' => 2, 'height' => 3],
            ['id' => 'stack_g3', 'name' => 'Goroutine 3', 'x' => 20, 'y' => 15, 'z' => 0, 'size' => 2, 'height' => 3],
            ['id' => 'channel', 'name' => 'Channel', 'x' => 0, 'y' => -5, 'z' => 0, 'size' => 3, 'height' => 3],
        ];
        
        foreach ($goroutine_sections as $section) {
            $nodes[] = [
                'id' => $section['id'],
                'name' => $section['name'],
                'type' => 'goroutine_section',
                'color' => getGoroutineSectionColor($section['id']),
                'position' => ['x' => $section['x'], 'y' => $section['y'], 'z' => $section['z']],
                'size' => $section['size'],
                'height' => $section['height'],
                'has_errors' => false,
                'error_count' => 0
            ];
        }
        
        $allocations = [
            ['id' => 'obj_main', 'name' => 'main object', 'x' => -20, 'y' => 5, 'z' => 5, 'size' => 1.5],
            ['id' => 'string_const', 'name' => '"Hello"', 'x' => -20, 'y' => 0, 'z' => 5, 'size' => 1.5],
            ['id' => 'frame_main', 'name' => 'main() frame', 'x' => -10, 'y' => 10, 'z' => 5, 'size' => 1.5],
            ['id' => 'frame_g1', 'name' => 'goroutine frame', 'x' => 0, 'y' => 10, 'z' => 5, 'size' => 1.5],
            ['id' => 'channel_buf', 'name' => 'channel buffer', 'x' => 0, 'y' => -10, 'z' => 5, 'size' => 2],
        ];
        
        foreach ($allocations as $alloc) {
            $nodes[] = [
                'id' => $alloc['id'],
                'name' => $alloc['name'],
                'type' => 'allocation',
                'color' => '#9b59b6',
                'position' => ['x' => $alloc['x'], 'y' => $alloc['y'], 'z' => $alloc['z']],
                'size' => $alloc['size'],
                'has_errors' => false,
                'error_count' => 0
            ];
        }
    }
    
    return [
        'nodes' => $nodes,
        'edges' => $edges,
        'view' => $view,
        'stage' => $stage,
        'session_id' => $result['session_id'] ?? ''
    ];
}

function getGoroutineSectionColor($section) {
    $colors = [
        'heap' => '#3498db',
        'stack_main' => '#2ecc71',
        'stack_g1' => '#f39c12',
        'stack_g2' => '#9b59b6',
        'stack_g3' => '#e74c3c',
        'channel' => '#1abc9c'
    ];
    return $colors[$section] ?? '#95a5a6';
}

function createGoASTNodes($node, &$nodes, &$edges, &$node_id, $x, $y, $z, $parent_id) {
    $current_id = $node_id++;
    $node_type = $node['type'] ?? 'Unknown';
    $node_name = $node['name'] ?? $node_type;
    
    $nodes[] = [
        'id' => 'ast_node_' . $current_id,
        'name' => $node_name,
        'type' => $node_type,
        'color' => getGoASTNodeColor($node_type),
        'position' => ['x' => $x, 'y' => $y, 'z' => $z],
        'size' => 2,
        'has_errors' => false,
        'error_count' => 0
    ];
    
    if ($parent_id >= 0) {
        $edges[] = [
            'from' => 'ast_node_' . $parent_id,
            'to' => 'ast_node_' . $current_id,
            'type' => 'parent_child',
            'color' => '#2ecc71'
        ];
    }
    
    $child_index = 0;
    foreach ($node as $key => $value) {
        if ($key === 'type' || $key === 'name') continue;
        
        if ($key === 'declarations' || $key === 'body' || $key === 'args' || $key === 'parameters') {
            if (is_array($value)) {
                foreach ($value as $child) {
                    if (is_array($child)) {
                        $child_x = $x + 15;
                        $child_y = $y - 8 - ($child_index * 8);
                        $child_z = $z + ($child_index * 5);
                        createGoASTNodes($child, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id);
                        $child_index++;
                    }
                }
            }
        } elseif (is_array($value) && isset($value['type'])) {
            $child_x = $x + 15;
            $child_y = $y - 8 - ($child_index * 8);
            $child_z = $z + ($child_index * 5);
            createGoASTNodes($value, $nodes, $edges, $node_id, $child_x, $child_y, $child_z, $current_id);
            $child_index++;
        }
    }
    
    return $current_id;
}

function getGoASTNodeColor($type) {
    $colors = [
        'File' => '#3498db',
        'Function' => '#2ecc71',
        'VariableDeclaration' => '#e74c3c',
        'PrintStatement' => '#f39c12',
        'ForLoop' => '#9b59b6',
        'Literal' => '#1abc9c',
        'Identifier' => '#e67e22',
        'BinaryExpression' => '#8e44ad'
    ];
    return $colors[$type] ?? '#95a5a6';
}

function getCFGNodeColor($type) {
    $colors = [
        'entry' => '#2ecc71',
        'exit' => '#e74c3c',
        'declaration' => '#3498db',
        'statement' => '#f39c12',
        'condition' => '#9b59b6'
    ];
    return $colors[$type] ?? '#95a5a6';
}

// If we reach here and no API endpoint was called, output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>3D Go Compiler Visualizer - Final Project</title>
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
        
        .go-key {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            padding: 10px;
            background: rgba(10, 25, 47, 0.5);
            border-radius: 6px;
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .go-key-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            color: #8892b0;
        }
        
        .go-key-item .key {
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
            <h1><i class="fab fa-golang"></i> 3D Go Compiler Visualizer</h1>
            <div class="subtitle">Interactive Visualization of Go Compilation Pipeline & Goroutines</div>
        </div>
        <div class="course-info">
            <span class="university">COMPUTER SCIENCE FINAL PROJECT - GO VERSION</span>
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
                    <h2>Go Compilation Controls</h2>
                </div>
                
                <div class="controls-scroll-container">
                    <div class="control-group">
                        <label for="view-mode"><i class="fas fa-eye"></i> View Mode:</label>
                        <select id="view-mode">
                            <option value="pipeline">Pipeline View</option>
                            <option value="ast">AST View</option>
                            <option value="cfg">Control Flow Graph</option>
                            <option value="goroutine">Goroutine Memory</option>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label for="stage-select"><i class="fas fa-code-branch"></i> Pipeline Stage:</label>
                        <select id="stage-select">
                            <option value="all">All Stages</option>
                            <option value="lexical">Lexical Analysis</option>
                            <option value="syntax">Syntax Analysis</option>
                            <option value="semantic">Semantic Analysis</option>
                            <option value="ssa">SSA Generation</option>
                            <option value="optimization">Optimization</option>
                            <option value="assembly">Assembly Generation</option>
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
                    
                    <div class="go-key">
                        <div class="go-key-item"><span class="key">:=</span> Short declaration</div>
                        <div class="go-key-item"><span class="key">go</span> Goroutine</div>
                        <div class="go-key-item"><span class="key">chan</span> Channel</div>
                        <div class="go-key-item"><span class="key">range</span> Range loop</div>
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
                        <h2>Go Source Code Editor</h2>
                    </div>
                    
                    <div class="code-editor">
                        <div class="code-editor-header">
                            <h3><i class="fab fa-golang"></i> Go Source Code</h3>
                            <select id="example-select">
                                <option value="">Load Example...</option>
                                <option value="simple">Simple Go Program</option>
                                <option value="goroutine">Goroutine Example</option>
                                <option value="channel">Channel Example</option>
                                <option value="struct">Struct Example</option>
                            </select>
                        </div>
                        <textarea id="source-code" spellcheck="false">package main

import "fmt"

func main() {
    x := 10
    y := 20
    result := x + y
    
    fmt.Println("Result:", result)
    
    for i := 0; i < 5; i++ {
        fmt.Println("Iteration", i)
    }
    
    if result > 25 {
        fmt.Println("Result is greater than 25")
    } else {
        fmt.Println("Result is 25 or less")
    }
    
    // Simple goroutine example
    go func() {
        fmt.Println("Hello from goroutine!")
    }()
}</textarea>
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
                            <i class="fas fa-info-circle"></i> Ready to compile Go code
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
                    <h2>Go Compilation Output</h2>
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
                        <button class="output-tab" data-output="ssa">
                            <i class="fas fa-microchip"></i> <span class="tab-text">SSA</span>
                        </button>
                        <button class="output-tab" data-output="assembly">
                            <i class="fas fa-file-code"></i> <span class="tab-text">Assembly</span>
                        </button>
                        <button class="output-tab" data-output="errors" id="errors-tab" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> <span class="tab-text">Errors</span>
                        </button>
                    </div>
                    
                    <!-- Output display area -->
                    <div id="tokens-output" class="output-display active"></div>
                    <div id="ast-output" class="output-display"></div>
                    <div id="ssa-output" class="output-display"></div>
                    <div id="assembly-output" class="output-display"></div>
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
                        <button id="download-assembly" class="btn secondary">
                            <i class="fas fa-download"></i> <span class="btn-text">Assembly</span>
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
                <span>Final Year Project - Computer Science Department | Go Compiler Design & Goroutine 3D Visualization System</span>
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
            <a href="https://github.com/Agabaofficial/go-compiler-3d-visualizer" target="_blank" class="github-link">
                <i class="fab fa-github"></i> View on GitHub
            </a>
        </div>
    </div>
    
    <div id="tooltip" class="tooltip"></div>

    <script>
        class GoCompilerVisualizer3D {
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
                
                // Handle mobile responsiveness
                window.addEventListener('resize', () => this.handleResize());
                
                // Initialize with default visualization
                this.createDefaultVisualization();
            }
            
            init() {
                const container = document.getElementById('visualization-canvas');
                
                // Scene
                this.scene = new THREE.Scene();
                this.scene.background = new THREE.Color(0x0a192f);
                
                // Camera
                this.camera = new THREE.PerspectiveCamera(60, container.clientWidth / container.clientHeight, 0.1, 1000);
                this.camera.position.set(0, 30, 50);
                
                // Renderer
                this.renderer = new THREE.WebGLRenderer({ antialias: true });
                this.renderer.setSize(container.clientWidth, container.clientHeight);
                this.renderer.shadowMap.enabled = true;
                this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
                container.appendChild(this.renderer.domElement);
                
                // Controls
                this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
                this.controls.enableDamping = true;
                this.controls.dampingFactor = 0.05;
                this.controls.minDistance = 10;
                this.controls.maxDistance = 200;
                
                // Lights
                const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
                this.scene.add(ambientLight);
                
                const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
                directionalLight.position.set(20, 40, 30);
                directionalLight.castShadow = true;
                directionalLight.shadow.mapSize.width = 1024;
                directionalLight.shadow.mapSize.height = 1024;
                this.scene.add(directionalLight);
                
                // Grid
                const gridHelper = new THREE.GridHelper(100, 20, 0x112240, 0x0a192f);
                gridHelper.position.y = -5;
                this.scene.add(gridHelper);
                
                // Setup raycasting for tooltips
                this.raycaster = new THREE.Raycaster();
                this.mouse = new THREE.Vector2();
                container.addEventListener('mousemove', (e) => this.onMouseMove(e));
            }
            
            bindEvents() {
                // Buttons
                document.getElementById('compile-btn').addEventListener('click', () => this.compile());
                document.getElementById('reset-btn').addEventListener('click', () => this.reset());
                document.getElementById('reset-view').addEventListener('click', () => this.resetView());
                document.getElementById('reset-camera').addEventListener('click', () => this.resetView());
                document.getElementById('screenshot').addEventListener('click', () => this.takeScreenshot());
                document.getElementById('zoom-in').addEventListener('click', () => this.zoom(1.2));
                document.getElementById('zoom-out').addEventListener('click', () => this.zoom(0.8));
                document.getElementById('output-toggle').addEventListener('click', () => this.toggleOutputPanel());
                
                // Controls
                document.getElementById('view-mode').addEventListener('change', (e) => {
                    this.currentView = e.target.value;
                    if (this.sessionId) this.visualize();
                });
                
                document.getElementById('stage-select').addEventListener('change', (e) => {
                    this.currentStage = e.target.value;
                    if (this.sessionId) this.visualize();
                });
                
                document.getElementById('auto-rotate').addEventListener('change', (e) => {
                    this.autoRotate = e.target.checked;
                });
                
                document.getElementById('show-labels').addEventListener('change', (e) => {
                    this.showLabels = e.target.checked;
                    this.toggleLabels();
                });
                
                document.getElementById('show-errors').addEventListener('change', (e) => {
                    this.showErrors = e.target.checked;
                    if (this.sessionId) this.visualize();
                });
                
                document.getElementById('example-select').addEventListener('change', (e) => {
                    this.loadExample(e.target.value);
                });
                
                // Output tabs
                document.querySelectorAll('.output-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        const outputType = e.target.dataset.output || e.target.closest('.output-tab').dataset.output;
                        this.showOutput(outputType);
                    });
                });
                
                // Download buttons
                document.getElementById('download-ast').addEventListener('click', () => this.download('ast'));
                document.getElementById('download-assembly').addEventListener('click', () => this.download('assembly'));
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
                
                if (!sourceCode.trim()) {
                    this.showStatus('Please enter Go source code', 'error');
                    return;
                }
                
                this.showStatus('Compiling Go code...', 'info');
                this.updateProgress(10);
                
                try {
                    const response = await fetch('?api=compile', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ source_code: sourceCode })
                    });
                    
                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    this.sessionId = data.session_id;
                    this.totalErrors = data.errors?.length || 0;
                    this.updateProgress(50);
                    
                    // Update all output displays
                    this.updateOutputs(data);
                    
                    // Show appropriate output tab
                    if (this.totalErrors > 0) {
                        this.showOutput('errors');
                        document.getElementById('errors-tab').style.display = 'flex';
                    } else {
                        this.showOutput('tokens');
                    }
                    
                    // Visualize
                    await this.visualize();
                    
                    this.updateProgress(100);
                    this.showStatus(
                        data.success ? 'Go compilation successful!' : 'Compilation completed with warnings',
                        data.success ? 'success' : 'warning'
                    );
                    
                } catch (error) {
                    this.showStatus('Error: ' + error.message, 'error');
                    this.updateProgress(0);
                    console.error('Compilation error:', error);
                }
            }
            
            // Update all outputs
            updateOutputs(data) {
                // Tokens output
                if (data.outputs && data.outputs.tokens) {
                    const tokensText = data.outputs.tokens.map(t => 
                        `Line ${t.line}: ${t.type.padEnd(15)} = "${t.value}"`
                    ).join('\n');
                    document.getElementById('tokens-output').textContent = tokensText;
                }
                
                // AST output
                if (data.outputs && data.outputs.ast) {
                    document.getElementById('ast-output').textContent = 
                        JSON.stringify(data.outputs.ast, null, 2);
                }
                
                // SSA output
                if (data.outputs && data.outputs.ssa) {
                    const ssaText = data.outputs.ssa.map((instr, idx) => {
                        const parts = [];
                        for (const [key, value] of Object.entries(instr)) {
                            if (key !== 'op') {
                                parts.push(`${key}: ${value}`);
                            }
                        }
                        return `${idx.toString().padStart(3)}: ${instr.op.padEnd(10)}` + 
                               (parts.length ? ' [' + parts.join(', ') + ']' : '');
                    }).join('\n');
                    document.getElementById('ssa-output').textContent = ssaText;
                }
                
                // Assembly output
                if (data.outputs && data.outputs.assembly) {
                    document.getElementById('assembly-output').textContent = 
                        Array.isArray(data.outputs.assembly) ? data.outputs.assembly.join('\n') : data.outputs.assembly;
                }
                
                // Errors output
                if (data.errors && data.errors.length > 0) {
                    const errorsText = data.errors.map((err, idx) => 
                        `${idx + 1}. ${err.type || 'Error'}:\n   ${err.message}\n   Line: ${err.line || 'N/A'}\n`
                    ).join('\n');
                    document.getElementById('errors-output').textContent = errorsText;
                    
                    // Update error section
                    let errorHtml = '';
                    data.errors.forEach((error, index) => {
                        const errorClass = error.severity === 'warning' ? 'error-warning' : '';
                        const icon = error.severity === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
                        errorHtml += `<div class="error-item ${errorClass}">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                <i class="fas ${icon}"></i>
                                <strong>${error.type || 'Error'}:</strong> ${error.message}
                            </div>
                            ${error.line ? `<div class="error-line" style="font-size: 0.85rem; color: #ffd166;">Line ${error.line}</div>` : ''}
                        </div>`;
                    });
                    document.getElementById('error-list').innerHTML = errorHtml;
                    document.getElementById('error-section').style.display = 'block';
                } else {
                    document.getElementById('error-section').style.display = 'none';
                }
                
                // Update stage info
                if (data.stages && data.stages.length > 0) {
                    let stageHtml = '<h3 style="color: #64ffda; margin-bottom: 12px; font-size: 1rem;">Go Compilation Pipeline</h3>';
                    data.stages.forEach(stage => {
                        const statusClass = `status-${stage.status || 'pending'}`;
                        const icon = stage.status === 'completed' ? 'fa-check-circle' : 
                                    stage.status === 'failed' ? 'fa-times-circle' : 'fa-clock';
                        stageHtml += `
                            <div style="margin-bottom: 10px; padding: 12px; background: rgba(100,255,218,0.05); border-radius: 6px; border: 1px solid rgba(100,255,218,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas ${icon}" style="color: ${stage.status === 'completed' ? '#64ffda' : stage.status === 'failed' ? '#ff6b6b' : '#8892b0'}"></i>
                                        <strong style="font-size: 0.9rem;">${stage.name}</strong>
                                    </div>
                                    <span class="stage-status ${statusClass}">${stage.status || 'pending'}</span>
                                </div>
                                <div style="font-size: 0.8rem; margin-top: 6px; color: #8892b0;">
                                    <i class="far fa-clock"></i> Duration: ${stage.duration}ms
                                    ${stage.errors && stage.errors.length > 0 ? 
                                        `<br><i class="fas fa-exclamation-circle"></i> Errors: ${stage.errors.length}` : ''}
                                </div>
                            </div>`;
                    });
                    document.getElementById('stage-info').innerHTML = stageHtml;
                }
            }
            
            // Show specific output tab
            showOutput(outputType) {
                // Update tab buttons
                document.querySelectorAll('.output-tab').forEach(tab => {
                    tab.classList.remove('active');
                    if (tab.dataset.output === outputType) {
                        tab.classList.add('active');
                    }
                });
                
                // Update output content
                document.querySelectorAll('.output-display').forEach(content => {
                    content.classList.remove('active');
                    if (content.id === `${outputType}-output`) {
                        content.classList.add('active');
                    }
                });
            }
            
            async visualize() {
                if (!this.sessionId) return;
                
                try {
                    const response = await fetch(
                        `?api=visualize&session_id=${this.sessionId}&stage=${this.currentStage}&view=${this.currentView}`
                    );
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    this.createVisualization(data);
                    
                } catch (error) {
                    console.error('Visualization error:', error);
                    this.showStatus('Visualization error: ' + error.message, 'error');
                }
            }
            
            createVisualization(data) {
                // Clear previous objects
                this.clearScene();
                
                if (!data.nodes || data.nodes.length === 0) {
                    // Create default visualization if no data
                    this.createDefaultVisualization();
                    return;
                }
                
                // Create nodes
                data.nodes.forEach(node => {
                    this.createNode(node);
                });
                
                // Create edges
                if (data.edges && data.edges.length > 0) {
                    data.edges.forEach(edge => {
                        this.createEdge(edge, data.nodes);
                    });
                }
                
                // Add labels if enabled
                if (this.showLabels) {
                    this.addLabels(data.nodes);
                }
            }
            
            createDefaultVisualization() {
                // Create a simple 3D visualization for testing
                const colors = [0x64ffda, 0x00d9a6, 0xff6b6b, 0xffa502, 0x9b59b6, 0x3498db];
                
                for (let i = 0; i < 6; i++) {
                    const geometry = new THREE.SphereGeometry(3, 32, 32);
                    const material = new THREE.MeshPhongMaterial({ 
                        color: colors[i],
                        emissive: colors[i],
                        emissiveIntensity: 0.2,
                        transparent: true,
                        opacity: 0.9
                    });
                    
                    const sphere = new THREE.Mesh(geometry, material);
                    sphere.position.set(i * 12 - 30, 0, 0);
                    sphere.castShadow = true;
                    this.scene.add(sphere);
                    this.objects.push(sphere);
                }
            }
            
            createNode(node) {
                let geometry, material;
                const color = new THREE.Color(node.color || '#64ffda');
                
                if (this.currentView === 'goroutine') {
                    geometry = new THREE.BoxGeometry(node.size || 3, node.height || 3, node.size || 3);
                } else if (node.type === 'entry' || node.type === 'exit') {
                    geometry = new THREE.ConeGeometry(node.size || 2.5, 5, 16);
                } else if (node.type === 'condition') {
                    geometry = new THREE.CylinderGeometry(node.size || 2.5, node.size || 2.5, 4, 16);
                } else {
                    geometry = new THREE.SphereGeometry(node.size || 2.5, 32, 32);
                }
                
                material = new THREE.MeshPhongMaterial({ 
                    color: color,
                    emissive: color,
                    emissiveIntensity: 0.2,
                    transparent: true,
                    opacity: 0.9,
                    shininess: 100
                });
                
                const mesh = new THREE.Mesh(geometry, material);
                mesh.position.set(
                    node.position?.x || 0,
                    node.position?.y || 0,
                    node.position?.z || 0
                );
                mesh.castShadow = true;
                mesh.receiveShadow = true;
                
                mesh.userData = node;
                this.scene.add(mesh);
                this.objects.push(mesh);
                
                // Add error indicator if node has errors
                if (node.has_errors && this.showErrors) {
                    this.addErrorIndicator(mesh);
                }
                
                // Add glow effect for important nodes
                if (node.type === 'entry' || node.type === 'exit' || node.type === 'channel') {
                    this.addGlowEffect(mesh);
                }
            }
            
            addErrorIndicator(mesh) {
                const node = mesh.userData;
                const geometry = new THREE.SphereGeometry((node.size || 2.5) * 1.3, 16, 16);
                const material = new THREE.MeshBasicMaterial({
                    color: 0xff6b6b,
                    transparent: true,
                    opacity: 0.3,
                    side: THREE.DoubleSide
                });
                
                const indicator = new THREE.Mesh(geometry, material);
                indicator.position.copy(mesh.position);
                this.scene.add(indicator);
                this.objects.push(indicator);
            }
            
            addGlowEffect(mesh) {
                const geometry = new THREE.SphereGeometry((mesh.userData.size || 2.5) * 1.5, 16, 16);
                const material = new THREE.MeshBasicMaterial({
                    color: 0x64ffda,
                    transparent: true,
                    opacity: 0.2,
                    side: THREE.DoubleSide
                });
                
                const glow = new THREE.Mesh(geometry, material);
                glow.position.copy(mesh.position);
                this.scene.add(glow);
                this.objects.push(glow);
            }
            
            createEdge(edge, nodes) {
                const fromNode = this.findObjectById(edge.from);
                const toNode = this.findObjectById(edge.to);
                
                if (!fromNode || !toNode) return;
                
                const curve = new THREE.CatmullRomCurve3([
                    fromNode.position.clone(),
                    new THREE.Vector3(
                        (fromNode.position.x + toNode.position.x) / 2,
                        (fromNode.position.y + toNode.position.y) / 2 + 8,
                        (fromNode.position.z + toNode.position.z) / 2
                    ),
                    toNode.position.clone()
                ]);
                
                const geometry = new THREE.TubeGeometry(curve, 20, 0.2, 8, false);
                const material = new THREE.MeshBasicMaterial({
                    color: this.getEdgeColor(edge.type),
                    transparent: true,
                    opacity: 0.7
                });
                
                const tube = new THREE.Mesh(geometry, material);
                this.scene.add(tube);
                this.objects.push(tube);
            }
            
            getEdgeColor(type) {
                const colors = {
                    'pipeline': 0x64ffda,
                    'parent_child': 0x00d9a6,
                    'control_flow': 0xffa502,
                    'conditional': 0xff6b6b,
                    'goroutine_flow': 0x9b59b6
                };
                return colors[type] || 0x8892b0;
            }
            
            addLabels(nodes) {
                // Clear existing labels
                this.labels.forEach(label => this.scene.remove(label));
                this.labels = [];
                
                nodes.forEach(node => {
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.width = 250;
                    canvas.height = 120;
                    
                    // Background with gradient
                    const gradient = context.createLinearGradient(0, 0, canvas.width, 0);
                    gradient.addColorStop(0, 'rgba(10, 25, 47, 0.9)');
                    gradient.addColorStop(1, 'rgba(17, 34, 64, 0.9)');
                    context.fillStyle = gradient;
                    context.fillRect(0, 0, canvas.width, canvas.height);
                    
                    // Border
                    context.strokeStyle = node.color || '#64ffda';
                    context.lineWidth = 2;
                    context.strokeRect(1, 1, canvas.width - 2, canvas.height - 2);
                    
                    // Title
                    context.font = 'bold 14px "Inter", sans-serif';
                    context.fillStyle = '#64ffda';
                    context.textAlign = 'center';
                    context.fillText(this.truncateText(node.name, 20), canvas.width / 2, 30);
                    
                    // Type
                    context.font = '12px "Inter", sans-serif';
                    context.fillStyle = '#8892b0';
                    context.fillText(`Type: ${node.type}`, canvas.width / 2, 55);
                    
                    // Status/Info
                    if (node.status) {
                        context.font = '11px "Inter", sans-serif';
                        context.fillStyle = node.status === 'completed' ? '#00d9a6' : 
                                          node.status === 'failed' ? '#ff6b6b' : '#ffa502';
                        context.fillText(`Status: ${node.status}`, canvas.width / 2, 75);
                    }
                    
                    if (node.error_count > 0) {
                        context.fillStyle = '#ff6b6b';
                        context.font = '11px "Inter", sans-serif';
                        context.fillText(`${node.error_count} error(s)`, canvas.width / 2, 95);
                    } else if (node.duration) {
                        context.fillStyle = '#8892b0';
                        context.font = '11px "Inter", sans-serif';
                        context.fillText(`Duration: ${node.duration}ms`, canvas.width / 2, 95);
                    }
                    
                    const texture = new THREE.CanvasTexture(canvas);
                    const material = new THREE.SpriteMaterial({ 
                        map: texture,
                        transparent: true 
                    });
                    const sprite = new THREE.Sprite(material);
                    
                    sprite.position.set(
                        node.position?.x || 0,
                        (node.position?.y || 0) + (node.height || node.size || 2.5) + 2,
                        node.position?.z || 0
                    );
                    
                    sprite.scale.set(10, 5, 1);
                    this.scene.add(sprite);
                    this.labels.push(sprite);
                });
            }
            
            truncateText(text, maxLength) {
                if (text.length <= maxLength) return text;
                return text.substring(0, maxLength) + '...';
            }
            
            toggleLabels() {
                this.labels.forEach(label => {
                    label.visible = this.showLabels;
                });
            }
            
            findObjectById(id) {
                return this.objects.find(obj => obj.userData?.id === id);
            }
            
            showStatus(message, type = 'info') {
                const statusEl = document.getElementById('status-message');
                const icon = type === 'error' ? 'fa-exclamation-circle' : 
                            type === 'warning' ? 'fa-exclamation-triangle' : 
                            type === 'success' ? 'fa-check-circle' : 'fa-info-circle';
                
                statusEl.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
                
                const progressBar = document.getElementById('progress-bar');
                progressBar.className = 'progress-fill';
                
                if (type === 'error') {
                    statusEl.style.color = '#ff6b6b';
                    progressBar.classList.add('error-indicator');
                } else if (type === 'warning') {
                    statusEl.style.color = '#ffa502';
                } else if (type === 'success') {
                    statusEl.style.color = '#00d9a6';
                } else {
                    statusEl.style.color = '#64ffda';
                }
            }
            
            updateProgress(percent) {
                document.getElementById('progress-bar').style.width = percent + '%';
            }
            
            reset() {
                this.sessionId = null;
                this.totalErrors = 0;
                
                // Reset source code to Go
                document.getElementById('source-code').value = `package main

import "fmt"

func main() {
    x := 10
    y := 20
    result := x + y
    
    fmt.Println("Result:", result)
    
    for i := 0; i < 5; i++ {
        fmt.Println("Iteration", i)
    }
    
    if result > 25 {
        fmt.Println("Result is greater than 25")
    } else {
        fmt.Println("Result is 25 or less")
    }
    
    // Simple goroutine example
    go func() {
        fmt.Println("Hello from goroutine!")
    }()
}`;
                
                // Reset outputs
                document.getElementById('tokens-output').textContent = '';
                document.getElementById('ast-output').textContent = '';
                document.getElementById('ssa-output').textContent = '';
                document.getElementById('assembly-output').textContent = '';
                document.getElementById('errors-output').textContent = '';
                
                // Reset tabs
                this.showOutput('tokens');
                document.getElementById('errors-tab').style.display = 'none';
                document.getElementById('error-section').style.display = 'none';
                
                document.getElementById('stage-info').innerHTML = `
                    <p><i class="fas fa-mouse-pointer"></i> Select a compilation stage or node to view details</p>
                    <div style="margin-top: 12px; padding: 12px; background: rgba(100,255,218,0.05); border-radius: 5px; border: 1px dashed rgba(100,255,218,0.2);">
                        <p style="color: #64ffda; margin-bottom: 6px; font-size: 0.9rem;"><i class="fas fa-lightbulb"></i> <strong>Tip:</strong></p>
                        <p style="font-size: 0.8rem; color: #8892b0;">Hover over nodes in the 3D visualization to see detailed information. Click and drag to rotate the view.</p>
                    </div>
                `;
                
                // Reset visualization
                this.clearScene();
                this.createDefaultVisualization();
                
                // Reset status
                this.updateProgress(0);
                this.showStatus('Ready to compile Go code');
            }
            
            clearScene() {
                this.objects.forEach(obj => {
                    this.scene.remove(obj);
                });
                this.objects = [];
                
                this.labels.forEach(label => {
                    this.scene.remove(label);
                });
                this.labels = [];
            }
            
            resetView() {
                this.camera.position.set(0, 30, 50);
                this.controls.reset();
            }
            
            zoom(factor) {
                this.camera.position.multiplyScalar(factor);
                this.controls.update();
            }
            
            takeScreenshot() {
                this.renderer.render(this.scene, this.camera);
                const dataURL = this.renderer.domElement.toDataURL('image/png');
                const link = document.createElement('a');
                link.href = dataURL;
                link.download = `go_compiler_visualization_${Date.now()}.png`;
                link.click();
            }
            
            async download(type) {
                if (!this.sessionId) {
                    this.showStatus('No compilation session found', 'error');
                    return;
                }
                
                this.showStatus(`Downloading ${type}...`, 'info');
                
                try {
                    const response = await fetch(`?api=download&session_id=${this.sessionId}&type=${type}`);
                    
                    if (!response.ok) {
                        throw new Error('Download failed');
                    }
                    
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    
                    if (type === 'assembly') {
                        a.download = `assembly_${this.sessionId}.asm`;
                    } else if (type === 'ast') {
                        a.download = `ast_${this.sessionId}.json`;
                    } else {
                        a.download = `main_${this.sessionId}.go`;
                    }
                    
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    this.showStatus(`${type} downloaded successfully`, 'success');
                    
                } catch (error) {
                    this.showStatus('Download failed: ' + error.message, 'error');
                }
            }
            
            loadExample(exampleId) {
                const examples = {
                    simple: `package main

import "fmt"

func main() {
    name := "Go"
    version := 1.21
    
    fmt.Printf("Welcome to %s version %.1f\\n", name, version)
    
    numbers := []int{1, 2, 3, 4, 5}
    sum := 0
    
    for _, num := range numbers {
        sum += num
    }
    
    fmt.Println("Sum:", sum)
}`,
                    goroutine: `package main

import (
    "fmt"
    "time"
)

func printNumbers(name string, count int) {
    for i := 1; i <= count; i++ {
        fmt.Printf("%s: %d\\n", name, i)
        time.Sleep(100 * time.Millisecond)
    }
}

func main() {
    // Start multiple goroutines
    go printNumbers("Goroutine A", 5)
    go printNumbers("Goroutine B", 5)
    
    // Wait for goroutines to complete
    time.Sleep(1 * time.Second)
    fmt.Println("All goroutines completed")
}`,
                    channel: `package main

import "fmt"

func main() {
    // Create a channel
    messages := make(chan string)
    
    // Start a goroutine that sends a message
    go func() {
        messages <- "Hello from channel!"
    }()
    
    // Receive the message from the channel
    msg := <-messages
    fmt.Println(msg)
    
    // Buffered channel example
    buffered := make(chan int, 2)
    buffered <- 1
    buffered <- 2
    
    fmt.Println(<-buffered)
    fmt.Println(<-buffered)
}`,
                    struct: `package main

import "fmt"

type Person struct {
    Name string
    Age  int
}

func (p Person) Greet() string {
    return fmt.Sprintf("Hello, my name is %s and I'm %d years old", p.Name, p.Age)
}

func main() {
    person := Person{
        Name: "Alice",
        Age:  30,
    }
    
    fmt.Println(person.Greet())
    
    // Pointer receiver method
    personPtr := &person
    personPtr.Age = 31
    fmt.Println(person.Greet())
}`
                };
                
                if (examples[exampleId]) {
                    document.getElementById('source-code').value = examples[exampleId];
                    this.showStatus(`Loaded Go example: ${exampleId}`, 'success');
                }
                
                document.getElementById('example-select').value = '';
            }
            
            onMouseMove(event) {
                const rect = this.renderer.domElement.getBoundingClientRect();
                this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
                this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
                
                this.raycaster.setFromCamera(this.mouse, this.camera);
                const intersects = this.raycaster.intersectObjects(this.objects);
                
                const tooltip = document.getElementById('tooltip');
                if (intersects.length > 0) {
                    const object = intersects[0].object;
                    const data = object.userData;
                    
                    if (data) {
                        tooltip.style.display = 'block';
                        tooltip.style.left = (event.clientX + 10) + 'px';
                        tooltip.style.top = (event.clientY + 10) + 'px';
                        
                        let html = `<div style="color: #64ffda; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">${this.truncateText(data.name, 25)}</div>`;
                        html += `<div style="margin-bottom: 4px; font-size: 0.8rem;"><strong>Type:</strong> ${data.type}</div>`;
                        
                        if (data.status) {
                            html += `<div style="margin-bottom: 4px; font-size: 0.8rem;"><strong>Status:</strong> ${data.status}</div>`;
                        }
                        
                        if (data.duration) {
                            html += `<div style="margin-bottom: 4px; font-size: 0.8rem;"><strong>Duration:</strong> ${data.duration}ms</div>`;
                        }
                        
                        if (data.error_count > 0) {
                            html += `<div style="color: #ff6b6b; margin-top: 6px; font-size: 0.8rem;"><i class="fas fa-exclamation-circle"></i> ${data.error_count} error(s)</div>`;
                        }
                        
                        tooltip.innerHTML = html;
                    }
                } else {
                    tooltip.style.display = 'none';
                }
            }
            
            handleResize() {
                const container = document.getElementById('visualization-canvas');
                this.camera.aspect = container.clientWidth / container.clientHeight;
                this.camera.updateProjectionMatrix();
                this.renderer.setSize(container.clientWidth, container.clientHeight);
                
                // Check if we're now mobile or desktop
                this.isMobile = window.innerWidth < 992;
            }
            
            animate() {
                this.animationId = requestAnimationFrame(() => this.animate());
                
                if (this.autoRotate) {
                    this.scene.rotation.y += 0.001;
                }
                
                this.controls.update();
                this.renderer.render(this.scene, this.camera);
            }
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', () => {
            window.goVisualizer = new GoCompilerVisualizer3D();
            
            // Handle mobile-specific adjustments on load
            if (window.innerWidth < 768) {
                // Hide some text on mobile for space
                document.querySelectorAll('.tab-text').forEach(el => {
                    if (window.innerWidth < 480) {
                        el.style.display = 'none';
                    }
                });
                
                document.querySelectorAll('.btn-text').forEach(el => {
                    if (window.innerWidth < 480) {
                        el.style.display = 'none';
                    }
                });
            }
            
            // Fix for textarea height on mobile
            if (window.innerWidth < 768) {
                const textarea = document.getElementById('source-code');
                textarea.style.minHeight = '150px';
            }
        });
    </script>
</body>
</html>