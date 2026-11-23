// Optional development relay: exposes only the webhook, not the whole local studio.
// Payload bytes and signature headers are forwarded unchanged. Never logs payloads/secrets.
import http from 'node:http';

const server = http.createServer((request, response) => {
  if (request.method === 'GET' && request.url === '/health') {
    response.writeHead(200, { 'Content-Type': 'text/plain' }).end('Webhook relay ready');
    return;
  }
  if (request.method !== 'POST' || request.url !== '/webhooks/pixelforge') {
    response.writeHead(404).end();
    return;
  }
  const chunks = [];
  let size = 0;
  request.on('data', (chunk) => {
    size += chunk.length;
    if (size > 131072) {
      response.writeHead(413).end();
      request.destroy();
      return;
    }
    chunks.push(chunk);
  });
  request.on('end', () => {
    if (size > 131072) return;
    const body = Buffer.concat(chunks);
    const headers = {
      'Content-Type': 'application/json',
      'Content-Length': body.length,
      Accept: 'application/json',
    };
    for (const key of [
      'pixelforge-signature',
      'pixelforge-event-id',
      'pixelforge-event-type',
      'pixelforge-delivery-attempt',
    ]) {
      if (request.headers[key]) headers[key] = request.headers[key];
    }
    const upstream = http.request(
      'http://127.0.0.1:8030/webhooks/pixelforge',
      { method: 'POST', headers, timeout: 8000 },
      (incoming) => {
        // Return status only so development exception bodies cannot escape through the relay.
        incoming.resume();
        incoming.on('end', () => response.writeHead(incoming.statusCode ?? 502).end());
      },
    );
    upstream.on('timeout', () => upstream.destroy());
    upstream.on('error', () => {
      if (!response.headersSent) response.writeHead(502).end();
    });
    upstream.end(body);
  });
});
server.listen(8031, '127.0.0.1', () =>
  console.log('Webhook-only relay listening on 127.0.0.1:8031'),
);
