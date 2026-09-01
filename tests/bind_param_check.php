<?php
/**
 * EXD — a static check for mismatched bind_param calls.
 *
 * mysqli only complains at run time, and only on the code path that reaches
 * the call — a checkout that binds 21 types to 22 variables passes every
 * syntax check and fails the first time someone tries to buy something. This
 * counts both sides of every literal bind_param in the tree and reports any
 * that disagree.
 *
 * It only inspects calls whose type string is a literal, because a computed
 * one cannot be counted without running the code. Those are rare and are
 * listed separately so they are not silently skipped.
 *
 *   php tests/bind_param_check.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

$skip = ['/.git/', '/node_modules/', '/vendor/', '/backend/', '/uploads/'];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

$mismatched = [];
$dynamic    = [];
$checked    = 0;

foreach ($files as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php') {
        continue;
    }
    foreach ($skip as $fragment) {
        if (str_contains($path, $fragment)) {
            continue 2;
        }
    }

    $source = (string) file_get_contents($path);
    $tokens = token_get_all($source);
    $count  = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        // Looking for  ->bind_param(
        if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== 'bind_param') {
            continue;
        }

        // Walk forward to the matching close paren, splitting on top-level commas.
        $j = $i + 1;
        while ($j < $count && $tokens[$j] !== '(') {
            $j++;
        }
        if ($j >= $count) {
            continue;
        }

        $depth     = 0;
        $arguments = [];
        $current   = [];

        for ($k = $j; $k < $count; $k++) {
            $t = $tokens[$k];

            if ($t === '(' || $t === '[' || $t === '{') {
                $depth++;
                if ($depth === 1) { continue; }
            } elseif ($t === ')' || $t === ']' || $t === '}') {
                $depth--;
                if ($depth === 0) {
                    if ($current !== []) { $arguments[] = $current; }
                    break;
                }
            } elseif ($t === ',' && $depth === 1) {
                $arguments[] = $current;
                $current = [];
                continue;
            }

            if ($depth >= 1) {
                $current[] = $t;
            }
        }

        if (!$arguments) {
            continue;
        }

        // The first argument must be a single quoted string to be countable.
        $first = array_values(array_filter($arguments[0], static fn($t): bool =>
            !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));

        $line = is_array($token) ? $token[2] : 0;

        if (count($first) !== 1 || !is_array($first[0]) || $first[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
            $dynamic[] = sprintf('%s:%d', str_replace($root . '/', '', $path), $line);
            continue;
        }

        $types     = trim($first[0][1], "'\"");
        $variables = count($arguments) - 1;
        $checked++;

        if (strlen($types) !== $variables) {
            $mismatched[] = sprintf(
                '%s:%d  types "%s" (%d) but %d variable%s bound',
                str_replace($root . '/', '', $path),
                $line,
                $types,
                strlen($types),
                $variables,
                $variables === 1 ? '' : 's'
            );
        }
    }
}

printf("Checked %d bind_param call%s with a literal type string.\n", $checked, $checked === 1 ? '' : 's');

if ($dynamic) {
    printf("%d call%s build the type string at run time and were not counted:\n",
        count($dynamic), count($dynamic) === 1 ? '' : 's');
    foreach ($dynamic as $entry) {
        echo '  - ' . $entry . "\n";
    }
}

if ($mismatched) {
    echo "\nMismatched:\n";
    foreach ($mismatched as $entry) {
        echo '  ' . $entry . "\n";
    }
    exit(1);
}

echo "Every counted call binds as many variables as its type string declares.\n";
exit(0);
