window.addEventListener('DOMContentLoaded', () => {
    if (typeof SwaggerUIBundle !== 'function') {
        document.getElementById('swagger-ui').textContent = 'The documentation could not be loaded. Please refresh the page.';
        return;
    }
    window.ui = SwaggerUIBundle({
        url: 'openapi.json',
        dom_id: '#swagger-ui',
        presets: [SwaggerUIBundle.presets.apis],
        deepLinking: true,
        withCredentials: true,
        validatorUrl: null,
        docExpansion: 'none',
        defaultModelsExpandDepth: 0,
        displayRequestDuration: true,
        filter: true,
    });
});
