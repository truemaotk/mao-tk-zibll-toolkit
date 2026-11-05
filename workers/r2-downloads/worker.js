const DOWNLOAD_PATH = '/v1/download';
const MAX_TTL_SECONDS = 20 * 60;
const CLOCK_SKEW_SECONDS = 30;
const encoder = new TextEncoder();
const decoder = new TextDecoder('utf-8', { fatal: true });

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (url.pathname !== DOWNLOAD_PATH) return reply(request, 404, 'Not found');
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return reply(request, 405, 'Method not allowed', { Allow: 'GET, HEAD' });
    }

    const tokens = url.searchParams.getAll('token');
    const token = tokens.length === 1 && tokens[0].length <= 6000
      ? /^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{43})$/.exec(tokens[0])
      : null;
    if (!token) return reply(request, 403, 'Download authorization required');

    const secret = env.MAOTK_R2_SIGNING_KEY;
    const bucket = env.DOWNLOADS_BUCKET;
    if (typeof secret !== 'string' || !/^[a-f0-9]{64}$/i.test(secret) || !bucket) {
      return reply(request, 503, 'Download service not configured');
    }

    try {
      const grant = await verifyGrant(token[1], token[2], secret);
      if (!grant) return reply(request, 403, 'Invalid or expired download authorization');
      return await sendObject(request, bucket, grant.key);
    } catch {
      return reply(request, 503, 'Download service temporarily unavailable');
    }
  },
};

function headersFor(extra) {
  const headers = new Headers(extra);
  headers.set('Cache-Control', 'private, no-store');
  headers.set('Referrer-Policy', 'no-referrer');
  headers.set('X-Content-Type-Options', 'nosniff');
  headers.set('X-Robots-Tag', 'noindex, nofollow');
  return headers;
}

function reply(request, status, text, extra) {
  const headers = headersFor(extra);
  headers.set('Content-Type', 'text/plain; charset=utf-8');
  return new Response(request.method === 'HEAD' ? null : text, { status, headers });
}

function base64UrlBytes(value) {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
  return Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '=')), c => c.charCodeAt(0));
}

async function verifyGrant(payload, signature, secret) {
  const key = await crypto.subtle.importKey(
    'raw', encoder.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['verify'],
  );
  const valid = await crypto.subtle.verify(
    'HMAC', key, base64UrlBytes(signature), encoder.encode('v1.' + payload),
  );
  if (!valid) return null;

  let grant;
  try {
    grant = JSON.parse(decoder.decode(base64UrlBytes(payload)));
  } catch {
    return null;
  }
  const now = Math.floor(Date.now() / 1000);
  if (!grant || typeof grant.key !== 'string' || !Number.isSafeInteger(grant.exp)
      || grant.exp <= now || grant.exp > now + MAX_TTL_SECONDS + CLOCK_SKEW_SECONDS
      || !grant.key || grant.key.endsWith('/') || /[\x00-\x1f\x7f]/.test(grant.key)
      || encoder.encode(grant.key).length > 1024) {
    return null;
  }
  return grant;
}

async function sendObject(request, bucket, key) {
  const rangeHeader = request.headers.get('Range');
  if (request.method === 'HEAD' || rangeHeader) {
    const object = await bucket.head(key);
    if (!object) return reply(request, 404, 'File not found');
    if (request.method === 'HEAD') return attachmentResponse(null, object, key);

    const ifRange = request.headers.get('If-Range');
    if (!ifRange || ifRange === object.httpEtag) {
      const range = parseRange(rangeHeader, object.size);
      if (!range) {
        return reply(request, 416, 'Range not satisfiable', { 'Content-Range': 'bytes */' + object.size });
      }
      const partial = await bucket.get(key, { range, onlyIf: { etagMatches: object.etag } });
      if (!partial) return reply(request, 404, 'File not found');
      if (!('body' in partial)) return reply(request, 412, 'File changed; request a new download');
      return attachmentResponse(partial.body, object, key, range);
    }
  }

  const object = await bucket.get(key);
  if (!object) return reply(request, 404, 'File not found');
  return attachmentResponse(object.body, object, key);
}

function parseRange(header, size) {
  const match = /^bytes=(\d{0,16})-(\d{0,16})$/i.exec(header);
  if (!match || size === 0 || (!match[1] && !match[2])) return null;
  if (!match[1]) {
    const count = Number(match[2]);
    if (!Number.isSafeInteger(count) || count === 0) return null;
    const length = Math.min(size, count);
    return { offset: size - length, length };
  }
  const offset = Number(match[1]);
  const end = match[2] ? Number(match[2]) : size - 1;
  if (!Number.isSafeInteger(offset) || !Number.isSafeInteger(end) || offset >= size || end < offset) return null;
  return { offset, length: Math.min(end, size - 1) - offset + 1 };
}

function attachmentResponse(body, object, key, range) {
  const name = key.slice(key.lastIndexOf('/') + 1).toWellFormed();
  const extension = name.match(/\.[a-z0-9]{1,10}$/i)?.[0] ?? '';
  const asciiName = /^[a-z0-9][a-z0-9 ._-]{0,127}$/i.test(name) ? name : 'download' + extension;
  const encodedName = encodeURIComponent(name).replace(/[!'()*]/g, char => '%' + char.charCodeAt(0).toString(16).toUpperCase());
  const length = range?.length ?? object.size;
  const headers = headersFor({
    'Content-Type': 'application/octet-stream',
    'Content-Disposition': 'attachment; filename="' + asciiName + '"; filename*=UTF-8\'\'' + encodedName,
    'Content-Length': String(length),
    'Accept-Ranges': 'bytes',
    ETag: object.httpEtag,
  });
  if (range) {
    headers.set('Content-Range', 'bytes ' + range.offset + '-' + (range.offset + range.length - 1) + '/' + object.size);
  }
  if (!body) return new Response(null, { status: 200, headers });

  // Workers derives Content-Length from the stream rather than trusting the header.
  const { readable, writable } = new FixedLengthStream(length);
  // pipeTo propagates stream failures to the response reader.
  void body.pipeTo(writable).catch(() => {});
  return new Response(readable, { status: range ? 206 : 200, headers });
}
