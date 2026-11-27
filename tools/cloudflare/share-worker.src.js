/**
 * SMART Circle share Worker (Cloudflare Workers, free plan).
 *
 * Why: InfinityFree checks every first visit with a small JavaScript test. Link-preview robots (WhatsApp,
 * Facebook, X, Telegram, LinkedIn...) cannot run it, so links straight to the site show no picture or title.
 * Shared links go through this Worker instead:
 *   - preview robots get a tiny page with the title, description and the SMART Circle share card;
 *   - people are sent straight on (302) to the same page on the real site.
 * Nothing is fetched from the site, so the Worker never meets the JavaScript test.
 *
 * Settings (Worker > Settings > Variables): ORIGIN = https://smartcircle.gt.tc (no slash at the end).
 */

const DEFAULT_ORIGIN = 'https://smartcircle.gt.tc';

const PREVIEW_ROBOTS = /facebookexternalhit|facebot|whatsapp|twitterbot|telegrambot|linkedinbot|slackbot|discordbot|pinterest|skypeuripreview|redditbot|applebot|googlebot|bingbot|duckduckbot|yandex|embedly|iframely|vkshare|viber|signal|snapchat|mastodon|bluesky|preview/i;

// What each shared page says in its preview card. Paths not listed use the default.
const PAGES = {
  '/': {
    title: 'SMART Circle: free lessons that show why it matters',
    description: 'Free recorded lessons, notes and handouts in Mathematics, Physics, Chemistry, Biology and English grammar for Form 1–4 students in Malawi. Every lesson shows where the topic is used in real life.',
  },
  '/about': {
    title: 'About SMART Circle: our story',
    description: 'Why SMART Circle exists: free, honest support for Form 1–4 students in Malawi that connects every topic to real life.',
  },
  '/library': {
    title: 'SMART Circle free library',
    description: 'Free printable handouts for Form 1–4 students in Malawi. No account needed.',
  },
  '/apply': {
    title: 'Apply to SMART Circle (free)',
    description: 'Free support for committed Form 1–4 students in Malawi: recorded lessons, notes, exercises with encouraging feedback.',
  },
};

const CARD_BASE64 = '__SHARE_CARD_PNG_BASE64__';

function escapeHtml(text) {
  return String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function describe(path, title) {
  if (PAGES[path]) {
    return PAGES[path];
  }
  if (path.startsWith('/library/notes/')) {
    return {
      title: title ? title + ' · SMART Circle free handout' : 'A free handout from SMART Circle',
      description: 'A free, printable handout for Form 1–4 students in Malawi, from SMART Circle. No account needed.',
    };
  }
  if (path.startsWith('/letters/')) {
    return { title: 'A letter from SMART Circle', description: 'Open the letter on the SMART Circle website.' };
  }
  return PAGES['/'];
}

function cardResponse() {
  const bytes = Uint8Array.from(atob(CARD_BASE64), (c) => c.charCodeAt(0));
  return new Response(bytes, { headers: { 'Content-Type': 'image/png', 'Cache-Control': 'public, max-age=604800' } });
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (url.pathname === '/share-card.png') {
      return cardResponse();
    }
    const origin = (env && env.ORIGIN ? env.ORIGIN : DEFAULT_ORIGIN).replace(/\/+$/, '');
    // Only site paths: letters, digits, dashes, slashes. Anything odd goes to the home page.
    const path = /^\/[A-Za-z0-9\/_-]*$/.test(url.pathname) ? url.pathname : '/';
    const title = (url.searchParams.get('t') || '').slice(0, 120);
    url.searchParams.delete('t');
    const query = url.searchParams.toString();
    const target = origin + path + (query ? '?' + query : '');
    const agent = request.headers.get('User-Agent') || '';

    if (!PREVIEW_ROBOTS.test(agent)) {
      return Response.redirect(target, 302);
    }

    const page = describe(path, title);
    const image = url.origin + '/share-card.png';
    const html = `<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>${escapeHtml(page.title)}</title>
<meta name="description" content="${escapeHtml(page.description)}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="SMART Circle">
<meta property="og:title" content="${escapeHtml(page.title)}">
<meta property="og:description" content="${escapeHtml(page.description)}">
<meta property="og:url" content="${escapeHtml(target)}">
<meta property="og:image" content="${escapeHtml(image)}">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta property="og:image:alt" content="SMART Circle: free lessons that show why it matters">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${escapeHtml(page.title)}">
<meta name="twitter:description" content="${escapeHtml(page.description)}">
<meta name="twitter:image" content="${escapeHtml(image)}">
<meta http-equiv="refresh" content="0; url=${escapeHtml(target)}">
</head><body><p><a href="${escapeHtml(target)}">${escapeHtml(page.title)}</a></p></body></html>`;

    return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'public, max-age=3600' } });
  },
};
