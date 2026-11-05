const IMAGE_PATH = /^\/v1\/images\/([a-f0-9]{64})\/([a-f0-9]{64})$/;
const SECRET_FORMAT = /^[a-f0-9]{64}$/i;
const IMAGE_TYPES = new Set(['image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp']);
const encoder = new TextEncoder();

function hexBytes(hex) {
  return Uint8Array.from(hex.match(/../g), byte => parseInt(byte, 16));
}

function toHex(bytes) {
  return Array.from(new Uint8Array(bytes), byte => byte.toString(16).padStart(2, '0')).join('');
}

function empty(status, headers = {}) {
  return new Response(null, { status, headers: { 'Cache-Control': 'no-store', ...headers } });
}

function image(body, contentType, fromBucket) {
  const type = contentType?.split(';', 1)[0].trim().toLowerCase();
  if (!IMAGE_TYPES.has(type)) return empty(502);

  return new Response(body, {
    headers: {
      'Content-Type': type,
      'X-Content-Type-Options': 'nosniff',
      'Cache-Control': fromBucket ? 'public, max-age=300' : 'no-store',
    },
  });
}

async function readOrigin(method, id, key, originSetting) {
  if (!originSetting) return empty(404);
  const origin = new URL(originSetting);
  if (origin.protocol !== 'https:' || origin.username || origin.password || origin.href !== `${origin.origin}/`) {
    return empty(503);
  }

  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(`origin\n${method}\n${id}\n${timestamp}`));
  const response = await fetch(new URL(`/wp-json/maotk-media/v1/source/${id}`, origin), {
    method,
    redirect: 'manual',
    cache: 'no-store',
    signal: AbortSignal.timeout(8000),
    headers: {
      'X-MaoTK-Media-Time': timestamp,
      'X-MaoTK-Media-Signature': toHex(signature),
    },
  });

  if (response.status === 404 || response.status === 410) return empty(404);
  if (response.status !== 200) return empty(502);
  return image(response.body, response.headers.get('Content-Type'), false);
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const match = IMAGE_PATH.exec(url.pathname);
    if (!match || url.search) return empty(404);
    if (request.method !== 'GET' && request.method !== 'HEAD') return empty(405, { Allow: 'GET, HEAD' });

    const secret = env.MAOTK_MEDIA_SIGNING_KEY;
    const bucket = env.MEDIA_BUCKET;
    if (typeof secret !== 'string' || !SECRET_FORMAT.test(secret) || !bucket) return empty(503);

    const [, id, signature] = match;
    try {
      const key = await crypto.subtle.importKey('raw', hexBytes(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign', 'verify']);
      const authorized = await crypto.subtle.verify('HMAC', key, hexBytes(signature), encoder.encode(`public\n${id}`));
      if (!authorized) return empty(404);

      const objectKey = `public/${id}`;
      const object = request.method === 'HEAD' ? await bucket.head(objectKey) : await bucket.get(objectKey);
      if (object) return image(object.body ?? null, object.httpMetadata?.contentType, true);

      return await readOrigin(request.method, id, key, env.MAOTK_MEDIA_WP_ORIGIN);
    } catch {
      return empty(502);
    }
  },
};
