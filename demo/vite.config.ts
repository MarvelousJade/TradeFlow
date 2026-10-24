import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import bootstrap from './bootstrap';

// Development-only disposable API. Never included by the production Vite build.
export default defineConfig({
  plugins: [
    react(),
    {
      name: 'disposable-booking-api',
      configureServer(server) {
        server.middlewares.use('/demo-api', (req, res) => {
          res.setHeader('Content-Type', 'application/json');
          res.setHeader('Cache-Control', 'no-store');
          const path = req.url?.split('?')[0];
          if (path === '/bootstrap' && req.method === 'GET') {
            res.end(JSON.stringify(bootstrap));
          } else if (path === '/eligibility' && req.method === 'POST') {
            let body = '';
            req.on('data', (chunk) => {
              body += chunk;
            });
            req.on('end', () => {
              try {
                const input = JSON.parse(body);
                const prefix = input.area_id === 2 ? 'M5V' : 'L5B';
                const eligible = String(input.postal_code)
                  .replace(/\s/g, '')
                  .toUpperCase()
                  .startsWith(prefix);
                res.end(
                  JSON.stringify({
                    eligible,
                    area: 'Demo area',
                    message: eligible
                      ? 'Demo coverage confirmed.'
                      : 'Outside demo coverage. Try M5V 2T6 in Toronto.',
                  }),
                );
              } catch {
                res.statusCode = 400;
                res.end(JSON.stringify({ message: 'Invalid demo request.' }));
              }
            });
          } else if (path === '/leads' && req.method === 'POST') {
            req.resume(); // Discard multipart data; do not store contact details or photos.
            res.statusCode = 201;
            res.end(
              JSON.stringify({
                reference: 'TF-DEMO',
                status: 'new',
                statusUrl: '',
                message: 'Demonstration complete. Nothing was saved or sent.',
              }),
            );
          } else {
            res.statusCode = 404;
            res.end(JSON.stringify({ message: 'Demo route not found.' }));
          }
        });
      },
    },
  ],
  server: { host: '127.0.0.1', port: 4173, strictPort: true },
});
