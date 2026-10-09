<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Mcp;

use Crustum\Ignis\Install\Mcp\FileWriter;
use ReflectionClass;
use stdClass;

test('constructor sets file path', function (): void {
    $writer = new FileWriter('/path/to/mcp.json');
    expect($writer)->toBeInstanceOf(FileWriter::class);
});

test('configKey method returns self for chaining', function (): void {
    $writer = new FileWriter('/path/to/mcp.json');
    $result = $writer->configKey('customKey');

    expect($result)->toBe($writer);
});

test('addServer method returns self for chaining', function (): void {
    $writer = new FileWriter('/path/to/mcp.json');
    $result = $writer
        ->configKey('servers')
        ->addServerConfig('test', [
            'command' => 'php',
            'args' => 'bin/cake.php',
            'env' => 'value',
        ]);

    expect($result)->toBe($writer);
});

test('save method returns boolean', function (): void {
    $path = prepareMcpFile(false);
    $writer = new FileWriter($path);
    $result = $writer->save();

    expect($result)->toBe(true);
});

test('written data is correct for brand new file', function (string $configKey, array $servers, string $expectedJson): void {
    $path = prepareMcpFile(false);

    $writer = (new FileWriter($path))
        ->configKey($configKey);

    foreach ($servers as $serverKey => $serverConfig) {
        $writer->addServerConfig($serverKey, $serverConfig);
    }

    $result = $writer->save();

    $simpleContents = preg_replace('/\s+/', '', mcpFileContents($path));
    expect($result)->toBe(true);
    expect($simpleContents)->toEqual($expectedJson);
})->with(newFileServerConfigurations());

test('updates existing plain JSON file using simple method', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-plain.json'));

    $result = (new FileWriter($path))
        ->configKey('servers')
        ->addServerConfig('new-server', [
            'command' => 'npm',
            'args' => ['start'],
        ])
        ->save();

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toHaveKey('existing')
        ->toHaveKey('other')
        ->toHaveKey('nested.key')
        ->toHaveKey('servers.new-server');

    expect($decoded['servers']['new-server']['command'])->toBe('npm');
});

test('adds to existing mcpServers in plain JSON', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-with-servers.json'));

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toHaveKey('mcpServers.existing-server')
        ->toHaveKey('mcpServers.ignis');

    expect($decoded['mcpServers']['ignis']['command'])->toBe('php');
});

test('preserves empty objects in existing plain JSON files', function (): void {
    $content = <<<'JSON'
    {
        "$schema": "https://opencode.ai/config.json",
        "mcp": {
            "existing": {
                "type": "remote",
                "enabled": true,
                "url": "https://example.com/mcp",
                "oauth": {}
            }
        }
    }
    JSON;

    $path = prepareMcpFile(true, $content);

    $result = (new FileWriter($path))
        ->configKey('mcp')
        ->addServerConfig('cake-ignis', [
            'type' => 'local',
            'enabled' => true,
            'command' => ['php', 'bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $decoded = json_decode(mcpFileContents($path));

    expect($result)->toBeTrue();
    expect($decoded->mcp->existing->oauth)->toBeInstanceOf(stdClass::class);
    expect($decoded->mcp->{'cake-ignis'}->command)->toBe(['php', 'bin/cake.php', 'ignis', 'mcp']);
});

test('preserves complex JSON5 features that VS Code supports', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp.json5'));

    $result = (new FileWriter($path))
        ->configKey('servers')
        ->addServerConfig('test', ['command' => 'cmd'])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue();
    expect($writtenContent)->toContain(
        '"test"',
        '// Here are comments within my JSON',
        "// I'm trailing",
        '// Ooo, pretty cool',
        'MYSQL_HOST',
    );
});

test('detects plain JSON with comments inside strings as safe', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-comments-in-strings.json'));

    $result = (new FileWriter($path))
        ->addServerConfig('new-server', ['command' => 'test-cmd'])
        ->save();

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);
    expect($decoded)->toHaveKey('exampleCode')
        ->toHaveKey('mcpServers.new-server');
    expect($decoded['exampleCode'])->toContain('// here is the example code');
});

test('hasUnquotedComments detects comments correctly', function (string $content, bool $expected, string $description): void {
    $writer = new FileWriter('/tmp/test.json');
    $reflection = new ReflectionClass($writer);
    $method = $reflection->getMethod('hasUnquotedComments');

    $result = $method->invokeArgs($writer, [$content]);

    expect($result)->toBe($expected, $description);
})->with(commentDetectionCases());

test('trailing comma detection works across newlines', function (string $content, bool $expected, string $description): void {
    $writer = new FileWriter('/tmp/test.json');
    $reflection = new ReflectionClass($writer);
    $method = $reflection->getMethod('isPlainJson');

    $result = $method->invokeArgs($writer, [$content]);

    expect($result)->toBe($expected, $description);
})->with(trailingCommaCases());

test('generateServerJson creates correct JSON snippet', function (): void {
    $writer = new FileWriter('/tmp/test.json');
    $reflection = new ReflectionClass($writer);
    $method = $reflection->getMethod('generateServerJson');

    $result = $method->invokeArgs($writer, ['ignis', ['command' => 'php']]);
    $expectedIgnis = "\"ignis\": {\n    \"command\": \"php\"\n}";
    expect($result)->toBe($expectedIgnis);

    $result = $method->invokeArgs($writer, ['mysql', [
        'command' => 'npx',
        'args' => ['@benborla29/mcp-server-mysql'],
        'env' => ['DB_HOST' => 'localhost'],
    ]]);
    $expectedMysql = "\"mysql\": {\n    \"command\": \"npx\",\n    \"args\": [\n        \"@benborla29/mcp-server-mysql\"\n    ],\n    \"env\": {\n        \"DB_HOST\": \"localhost\"\n    }\n}";
    expect($result)->toBe($expectedMysql);
});

test('fixture mcp-no-configkey.json5 is detected as JSON5 and will use injectNewConfigKey', function (): void {
    $content = fixtureContent('mcp-no-configkey.json5');
    $writer = new FileWriter('/tmp/test.json');
    $reflection = new ReflectionClass($writer);

    $isPlainJsonMethod = $reflection->getMethod('isPlainJson');

    $isPlainJson = $isPlainJsonMethod->invokeArgs($writer, [$content]);
    expect($isPlainJson)->toBeFalse('Should be detected as JSON5 due to comments');

    $configKeyPattern = '/["\']mcpServers["\']\\s*:\\s*\\{/';
    $hasConfigKey = preg_match($configKeyPattern, $content);
    expect($hasConfigKey)->toBe(0, 'Should not have mcpServers key, triggering injectNewConfigKey');
});

test('injects new configKey when it does not exist', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-no-configkey.json5'));

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue();
    expect($writtenContent)->toContain(
        '"mcpServers"',
        '"ignis"',
        '"php"',
        '// No mcpServers key at all',
    );
});

test('injects into existing configKey preserving JSON5 features', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp.json5'));

    $result = (new FileWriter($path))
        ->configKey('servers')
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue();
    expect($writtenContent)->toContain(
        '"ignis"',
        'mysql',
        'cake-ignis',
        '// Here are comments within my JSON',
        '// Ooo, pretty cool',
    );
});

test("injecting twice into existing JSON 5 doesn't cause duplicates", function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp.json5'));

    $result = (new FileWriter($path))
        ->configKey('servers')
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $capturedContent = mcpFileContents($path);
    $ignisCounts = substr_count($capturedContent, '"ignis": {');
    expect($result)->toBeTrue();
    expect($ignisCounts)->toBe(1);
    expect($capturedContent)->toContain(
        '"ignis"',
        'mysql',
        'cake-ignis',
        '// Here are comments within my JSON',
        '// Ooo, pretty cool',
    );

    $result = (new FileWriter($path))
        ->configKey('servers')
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    expect($result)->toBeTrue();

    $ignisCounts = substr_count(mcpFileContents($path), '"ignis": {');
    expect($ignisCounts)->toBe(1);
});

test('injects into empty configKey object', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-empty-configkey.json5'));

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue();
    expect($writtenContent)->toContain(
        '"ignis"',
        '// Empty mcpServers object',
        'test_input',
    );
});

test('preserves trailing commas when injecting into existing servers', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-trailing-comma.json5'));

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($writtenContent)->toContain(
            '"ignis"',
            'existing-server',
            '// Trailing comma here',
            'arg1',
        );
});

test('updates JSON5 file with only single-quoted strings', function (): void {
    $singleQuotedJson5 = <<<'JSON5'
    {
        'mcpServers': {
            'existing': {
                'command': 'node'
            }
        }
    }
    JSON5;

    $path = prepareMcpFile(true, $singleQuotedJson5);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue();
    expect($writtenContent)->toContain('"ignis"', "'existing'");
});

test('save writes servers when the existing file is whitespace only', function (): void {
    $path = prepareMcpFile(true, "\n  \n");

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', ['command' => 'php'])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($writtenContent)->toContain('"ignis"')
        ->and($writtenContent)->toContain('php');
});

test('save updates a plain JSON file that starts with a UTF-8 BOM', function (): void {
    $content = "\xEF\xBB\xBF" . json_encode(['mcpServers' => ['existing' => ['command' => 'existing-cmd']]]);
    $path = prepareMcpFile(true, $content);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', ['command' => 'php'])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($writtenContent)->not->toStartWith("\xEF\xBB\xBF")
        ->and($writtenContent)->toContain('"existing"')
        ->and($writtenContent)->toContain('"ignis"');
});

test('save rejects plain JSON files without an object root', function (string $content): void {
    $path = prepareMcpFile(true, $content);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', ['command' => 'php'])
        ->save();

    expect($result)->toBeFalse();
    expect(mcpFileContents($path))->toBe($content);
})->with([
    'null' => 'null',
    'boolean' => 'true',
    'number' => '1',
    'string' => '"config"',
    'array' => '[]',
]);

test('save rejects JSON5 files without an object root', function (string $content): void {
    $path = prepareMcpFile(true, $content);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', ['command' => 'php'])
        ->save();

    expect($result)->toBeFalse();
    expect(mcpFileContents($path))->toBe($content);
})->with([
    'null' => 'null // comment',
    'boolean' => 'true // comment',
    'number' => '1 /* comment */',
    'string' => "'config'",
    'array' => '[{ unquoted: true, }]',
]);

test('adds the comma outside a trailing comment in the servers object', function (): void {
    $contentWithTrailingComment = <<<'JSON5'
    {
      "mcpServers": {
        "context7": {
          "command": "npx"
        } // docs lookup
      }
    }
    JSON5;

    $path = prepareMcpFile(true, $contentWithTrailingComment);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);
    $withoutComments = preg_replace('/\/\/[^\n]*/', '', $writtenContent);

    expect($result)->toBeTrue()
        ->and(json_decode((string)$withoutComments, true))->not->toBeNull()
        ->and($writtenContent)->toContain(
            '"ignis"',
            '"context7"',
            '// docs lookup',
        );
});

test('does not read a // inside a single-quoted string as a comment', function (): void {
    $singleQuotedUrl = <<<'JSON5'
    {
      'mcpServers': {
        'remote': { 'url': 'https://example.test/sse' }
      }
    }
    JSON5;

    $path = prepareMcpFile(true, $singleQuotedUrl);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($writtenContent)->toContain(
            "'url': 'https://example.test/sse'",
            '"ignis"',
        );
});

test('injects into an unquoted JSON5 config key', function (): void {
    $unquotedJson5 = <<<'JSON5'
    {
      mcpServers: {
        existing: {
          command: 'node'
        }
      }
    }
    JSON5;

    $path = prepareMcpFile(true, $unquotedJson5);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($writtenContent)->toContain('"ignis"', 'existing')
        ->and($writtenContent)->toContain("\n    \"ignis\"")
        ->and(substr_count($writtenContent, 'mcpServers'))->toBe(1);
});

test('injects a server that is only present as a comment', function (): void {
    $commentedOutJson5 = <<<'JSON5'
    {
        "mcpServers": {
            // "ignis": { "command": "php", "args": ["bin/cake.php", "ignis", "mcp"] }
        }
    }
    JSON5;

    $path = prepareMcpFile(true, $commentedOutJson5);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    expect($result)->toBeTrue();
    expect(normalizeMcpLineEndings(mcpFileContents($path)))->toBe(normalizeMcpLineEndings(<<<'JSON5'
    {
        "mcpServers": {
            "ignis": {
                "command": "php",
                "args": [
                    "bin/cake.php",
                    "ignis",
                    "mcp"
                ]
            }
            // "ignis": { "command": "php", "args": ["bin/cake.php", "ignis", "mcp"] }
        }
    }

    JSON5));
});

test('injects a server that is only present as a block comment', function (): void {
    $commentedOutJson5 = <<<'JSON5'
    {
        "mcpServers": {
            /* "ignis": { "command": "php" } */
        }
    }
    JSON5;

    $path = prepareMcpFile(true, $commentedOutJson5);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    expect($result)->toBeTrue();
    expect(normalizeMcpLineEndings(mcpFileContents($path)))->toBe(normalizeMcpLineEndings(<<<'JSON5'
    {
        "mcpServers": {
            "ignis": {
                "command": "php",
                "args": [
                    "bin/cake.php",
                    "ignis",
                    "mcp"
                ]
            }
            /* "ignis": { "command": "php" } */
        }
    }

    JSON5));
});

test('injects into the real configKey when a commented-out copy of it comes first', function (): void {
    $json5 = <<<'JSON5'
    {
        // "mcpServers": {
        //     "ignis": { "command": "php" }
        // }
        "mcpServers": {
            "other": { "command": "x" } // has a } brace
        }
    }
    JSON5;

    $path = prepareMcpFile(true, $json5);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', ['command' => 'php'])
        ->save();

    expect($result)->toBeTrue();
    expect(normalizeMcpLineEndings(mcpFileContents($path)))->toBe(normalizeMcpLineEndings(<<<'JSON5'
    {
        // "mcpServers": {
        //     "ignis": { "command": "php" }
        // }
        "mcpServers": {
            "other": { "command": "x" }, // has a } brace
            "ignis": {
                "command": "php"
            }
        }
    }

    JSON5));
});

test('new file ends with a trailing newline', function (): void {
    $path = prepareMcpFile(false);

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    expect($result)->toBeTrue();
    expect(mcpFileContents($path))->toEndWith("\n");
});

test('updated plain JSON file ends with a trailing newline', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp-with-servers.json'));

    $result = (new FileWriter($path))
        ->addServerConfig('ignis', ['command' => 'php'])
        ->save();

    expect($result)->toBeTrue();
    expect(mcpFileContents($path))->toEndWith("\n");
});

test('updated JSON5 file ends with a single trailing newline', function (): void {
    $path = prepareMcpFile(true, fixtureContent('mcp.json5'));

    $result = (new FileWriter($path))
        ->configKey('servers')
        ->addServerConfig('test', ['command' => 'cmd'])
        ->save();

    $writtenContent = mcpFileContents($path);

    expect($result)->toBeTrue();
    expect($writtenContent)->toEndWith("\n");
    expect($writtenContent)->not->toEndWith("\n\n");
});

test('detectIndentation works correctly with various patterns', function (string $content, int $position, int $expected, string $description): void {
    $writer = new FileWriter('/tmp/test.json');

    $result = $writer->detectIndentation($content, $position);

    expect($result)->toBe($expected, $description);
})->with(indentationDetectionCases());

function newFileServerConfigurations(): array
{
    return [
        'single server without args or env' => [
            'servers',
            [
                'im-new-here' => ['command' => './start-mcp'],
            ],
            '{"servers":{"im-new-here":{"command":"./start-mcp"}}}',
        ],
        'single server with args' => [
            'mcpServers',
            [
                'ignis' => [
                    'command' => 'php',
                    'args' => ['bin/cake.php', 'ignis', 'mcp'],
                ],
            ],
            '{"mcpServers":{"ignis":{"command":"php","args":["bin/cake.php","ignis","mcp"]}}}',
        ],
        'single server with env' => [
            'servers',
            [
                'mysql' => [
                    'command' => 'npx',
                    'env' => ['DB_HOST' => 'localhost', 'DB_PORT' => '3306'],
                ],
            ],
            '{"servers":{"mysql":{"command":"npx","env":{"DB_HOST":"localhost","DB_PORT":"3306"}}}}',
        ],
        'multiple servers mixed' => [
            'mcpServers',
            [
                'ignis' => [
                    'command' => 'php',
                    'args' => ['bin/cake.php', 'ignis', 'mcp'],
                ],
                'mysql' => [
                    'command' => 'npx',
                    'args' => ['@benborla29/mcp-server-mysql'],
                    'env' => ['DB_HOST' => 'localhost'],
                ],
            ],
            '{"mcpServers":{"ignis":{"command":"php","args":["bin/cake.php","ignis","mcp"]},"mysql":{"command":"npx","args":["@benborla29/mcp-server-mysql"],"env":{"DB_HOST":"localhost"}}}}',
        ],
        'custom config key' => [
            'customKey',
            [
                'test' => ['command' => 'test-cmd'],
            ],
            '{"customKey":{"test":{"command":"test-cmd"}}}',
        ],
    ];
}

function commentDetectionCases(): array
{
    return [
        'plain JSON no comments' => [
            '{"servers": {"test": {"command": "npm"}}}',
            false,
            'Plain JSON should return false',
        ],
        'JSON with comments in strings' => [
            '{"exampleCode": "// here is the example code\n<?php", "url": "https://example.com/path"}',
            false,
            'Comments inside strings should not be detected as real comments',
        ],
        'JSON5 with real line comments' => [
            '{"servers": {"test": "value"} // this is a real comment}',
            true,
            'Real JSON5 line comments should be detected',
        ],
        'JSON5 with comment at start of line' => [
            '{\n  // This is a comment\n  "servers": {}\n}',
            true,
            'Line comments at start should be detected',
        ],
        'complex string with escaped quotes' => [
            '{"code": "console.log(\\"// not a comment\\");", "other": "value"}',
            false,
            'Comments in strings with escaped quotes should not be detected',
        ],
        'multiple comments in strings' => [
            '{"example1": "// comment 1", "example2": "some // comment 2 here"}',
            false,
            'Multiple comments in different strings should not be detected',
        ],
        'mixed real and string comments' => [
            '{"example": "// fake comment"} // real comment',
            true,
            'Should detect real comment even when fake ones exist in strings',
        ],
        'empty string' => [
            '',
            false,
            'Empty string should return false',
        ],
        'single slash not comment' => [
            '{"path": "/usr/bin/test"}',
            false,
            'Single slash should not be detected as comment',
        ],
        'block comment outside strings' => [
            '{ /* server config */ "servers": {} }',
            true,
            'Block comments outside strings should be detected',
        ],
        'block comment in string' => [
            '{"code": "/* not a real comment */", "other": "value"}',
            false,
            'Block comments inside strings should not be detected',
        ],
    ];
}

function trailingCommaCases(): array
{
    return [
        'valid JSON no trailing comma' => [
            '{"servers": {"test": "value"}}',
            true,
            'Valid JSON should return true (is plain JSON)',
        ],
        'trailing comma in object same line' => [
            '{"servers": {"test": "value",}}',
            false,
            'Trailing comma in object should return false (is JSON5)',
        ],
        'trailing comma in array same line' => [
            '{"items": ["a", "b", "c",]}',
            false,
            'Trailing comma in array should return false (is JSON5)',
        ],
        'trailing comma across newlines in object' => [
            "{\n  \"servers\": {\n    \"test\": \"value\",\n  }\n}",
            false,
            'Trailing comma across newlines in object should be detected',
        ],
        'trailing comma across newlines in array' => [
            "{\n  \"items\": [\n    \"a\",\n    \"b\",\n  ]\n}",
            false,
            'Trailing comma across newlines in array should be detected',
        ],
        'trailing comma with tabs and spaces' => [
            "{\n  \"test\": \"value\",\t \n}",
            false,
            'Trailing comma with mixed whitespace should be detected',
        ],
        'comma in string not trailing' => [
            '{"example": "value,", "other": "test"}',
            true,
            'Comma inside string should not be detected as trailing',
        ],
    ];
}

function indentationDetectionCases(): array
{
    return [
        'mcp.json5 servers indentation' => [
            "{\n    // Here are comments within my JSON\n    \"servers\": {\n        \"mysql\": {\n            \"command\": \"npx\"\n        },\n        \"cake-ignis\": {\n            \"command\": \"php\"\n        }\n    },\n    \"inputs\": []\n}",
            200,
            8,
            'Should detect 8 spaces for server definitions in mcp.json5',
        ],
        'nested object with 4-space base indent' => [
            "{\n    \"config\": {\n        \"server1\": {\n            \"command\": \"test\"\n        }\n    }\n}",
            80,
            8,
            'Should detect 8 spaces for nested server definitions',
        ],
        'no previous server definitions' => [
            "{\n    \"inputs\": []\n}",
            20,
            8,
            'Should fallback to 8 spaces when no server definitions found',
        ],
        'deeper nesting with 2-space indent' => [
            "{\n  \"config\": {\n    \"servers\": {\n      \"mysql\": {\n        \"command\": \"test\"\n      }\n    }\n  }\n}",
            80,
            6,
            'Should detect correct indentation in deeply nested structures',
        ],
        'single server definition at root level' => [
            "{\n\"mysql\": {\n  \"command\": \"npx\"\n}\n}",
            30,
            0,
            'Should detect no indentation for root-level server definitions',
        ],
        'multiple server definitions with consistent indentation' => [
            "{\n    \"servers\": {\n        \"mysql\": {\n            \"command\": \"npx\"\n        },\n        \"postgres\": {\n            \"command\": \"pg\"\n        }\n    }\n}",
            150,
            8,
            'Should consistently detect indentation across multiple servers',
        ],
        'server definition with comments' => [
            "{\n    // Comment here\n    \"servers\": {\n        \"mysql\": { // inline comment\n            \"command\": \"npx\"\n        }\n    }\n}",
            120,
            8,
            'Should detect indentation correctly when comments are present',
        ],
        'empty content' => [
            '',
            0,
            8,
            'Should fallback to 8 spaces for empty content',
        ],
        'empty configKey object' => [
            "{\n  \"mcpServers\": {\n  }\n}",
            25,
            4,
            'Should indent one level deeper than the configKey line when it has no servers',
        ],
    ];
}

/**
 * Normalize line endings for exact-output assertions (test files use CRLF).
 *
 * @param string $content Content with mixed line endings
 * @return string
 */
function normalizeMcpLineEndings(string $content): string
{
    return str_replace("\r\n", "\n", $content);
}
