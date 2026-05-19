<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <link rel="stylesheet" type="text/css" href="/vendor/swagger-ui/swagger-ui.css">
    <style>
        html {
            height: 100%;
        }
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #f7f7f7;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>

    <script src="/vendor/swagger-ui/swagger-ui-bundle.js"></script>
    <script>
        const ui = SwaggerUIBundle({
            url: '{{ url('/api-docs/openapi.json') }}',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.SwaggerUIStandalonePreset
            ],
            layout: 'BaseLayout'
        });
    </script>
</body>
</html>
