<?php

declare(strict_types=1);

namespace Shardevcom\Documentation\Exporters;

use Illuminate\Support\Facades\File;
use Shardevcom\Documentation\DTO\GraphQLDocumentation;

/**
 * Exports documentation as HTML (Swagger UI / Redoc compatible).
 *
 * Boundary: generates static HTML documentation with embedded OpenAPI spec.
 */
final class HtmlExporter
{
    public function export(array $openapi, GraphQLDocumentation $graphql, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Generate main index.html with Swagger UI
        $html = $this->generateSwaggerUiHtml($openapi, $graphql);
        File::put($outputPath, $html);

        // Also generate Redoc version
        $redocPath = Str::replaceLast('.html', '-redoc.html', $outputPath);
        $redocHtml = $this->generateRedocHtml($openapi, $graphql);
        File::put($redocPath, $redocHtml);

        // Generate GraphQL SDL page
        $graphqlPath = Str::replaceLast('.html', '-graphql.html', $outputPath);
        $graphqlHtml = $this->generateGraphQLHtml($graphql);
        File::put($graphqlPath, $graphqlHtml);
    }

    private function generateSwaggerUiHtml(array $openapi, GraphQLDocumentation $graphql): string
    {
        $specJson = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $graphqlSdl = $graphql->schemaSdl;
        $title = $openapi['info']['title'] ?? 'API Documentation';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <style>
        html, body { margin: 0; padding: 0; height: 100%; }
        #swagger-ui { height: 100vh; }
        .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        const spec = {$specJson};
        const graphqlSdl = `{$graphqlSdl}`;

        window.onload = function() {
            const ui = SwaggerUIBundle({
                spec: spec,
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true,
            });

            // Add GraphQL tab
            setTimeout(() => {
                const navbar = document.querySelector('.swagger-ui .topbar');
                if (navbar) {
                    const graphqlLink = document.createElement('a');
                    graphqlLink.href = 'docs-graphql.html';
                    graphqlLink.textContent = 'GraphQL';
                    graphqlLink.style.marginLeft = '20px';
                    graphqlLink.style.color = '#3b4151';
                    graphqlLink.style.textDecoration = 'none';
                    graphqlLink.style.fontWeight = '500';
                    navbar.appendChild(graphqlLink);
                }
            }, 100);
        };
    </script>
</body>
</html>
HTML;
    }

    private function generateRedocHtml(array $openapi, GraphQLDocumentation $graphql): string
    {
        $specJson = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $title = $openapi['info']['title'] ?? 'API Documentation';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Redoc</title>
    <style>
        body { margin: 0; padding: 0; }
        redoc { display: block; height: 100vh; }
    </style>
</head>
<body>
    <redoc spec-url='data:application/json;charset=utf-8,'.encodeURIComponent(JSON.stringify({$specJson}))'></redoc>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
HTML;
    }

    private function generateGraphQLHtml(GraphQLDocumentation $graphql): string
    {
        $sdl = htmlspecialchars($graphql->schemaSdl);
        $introspectionJson = json_encode($graphql->introspection, JSON_PRETTY_PRINT);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GraphQL Schema Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/graphiql@2.4.7/graphiql.min.css" />
    <style>
        html, body { margin: 0; padding: 0; height: 100%; }
        #graphiql { height: 100vh; }
        .schema-tabs { display: flex; border-bottom: 1px solid #ddd; padding: 10px; background: #f5f5f5; }
        .schema-tab { padding: 8px 16px; cursor: pointer; border: none; background: transparent; }
        .schema-tab.active { border-bottom: 2px solid #007acc; font-weight: bold; }
        .schema-content { display: none; padding: 20px; }
        .schema-content.active { display: block; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow: auto; }
    </style>
</head>
<body>
    <div class="schema-tabs">
        <button class="schema-tab active" onclick="showTab('sdl')">Schema (SDL)</button>
        <button class="schema-tab" onclick="showTab('introspection')">Introspection (JSON)</button>
        <button class="schema-tab" onclick="showTab('graphiql')">GraphiQL Explorer</button>
        <a href="index.html" style="margin-left: auto; padding: 8px 16px; background: #007acc; color: white; text-decoration: none; border-radius: 4px;">REST API Docs</a>
    </div>

    <div id="sdl" class="schema-content active">
        <pre><code>{$sdl}</code></pre>
    </div>

    <div id="introspection" class="schema-content">
        <pre><code>{$introspectionJson}</code></pre>
    </div>

    <div id="graphiql" class="schema-content">
        <div id="graphiql-container" style="height: calc(100vh - 60px);"></div>
    </div>

    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/graphiql@2.4.7/graphiql.min.js"></script>
    <script>
        function showTab(tab) {
            document.querySelectorAll('.schema-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.schema-tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');

            if (tab === 'graphiql' && !window.graphiqlLoaded) {
                loadGraphiQL();
            }
        }

        function loadGraphiQL() {
            const introspection = {$introspectionJson};

            const fetcher = async (params) => {
                const response = await fetch('/graphql', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(params),
                });
                return response.json();
            };

            ReactDOM.render(
                React.createElement(GraphiQL, {
                    fetcher: fetcher,
                    schema: introspection.__schema,
                    defaultQuery: `query {\n  products {\n    data {\n      id\n      sku\n      name\n    }\n  }\n}`,
                }),
                document.getElementById('graphiql-container')
            );
            window.graphiqlLoaded = true;
        }
    </script>
</body>
</html>
HTML;
    }
}