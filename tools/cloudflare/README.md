# Share Worker: link previews for SMART Circle

**The problem.** InfinityFree checks each first visit with a small JavaScript test. The robots that WhatsApp,
Facebook, X, Telegram and LinkedIn send to build a link preview cannot run it, so a link straight to
`smartcircle.gt.tc` shows as a bare link, with no picture and no title.

**The fix.** Share buttons on the site point at this free Cloudflare Worker instead:

- preview robots get a small page with the title, a description and the SMART Circle share card (1200 × 630);
- people are sent straight on (a normal redirect) to the same page on the real site.

The Worker never contacts the site, so it never meets the JavaScript test. It is free on Cloudflare's free plan.

## Set it up once (about 10 minutes, no programming)

1. Create a free account at **dash.cloudflare.com** and open **Workers & Pages → Create → Create Worker**.
2. Name it `smartcircle-share` and press **Deploy** (it creates a "Hello World" Worker).
3. Press **Edit code**, delete everything in the editor, and paste the whole of
   [`share-worker.js`](share-worker.js) (it already contains the share card). Press **Deploy**.
4. In the Worker's **Settings → Variables and Secrets**, add a text variable `ORIGIN` = `https://smartcircle.gt.tc`.
5. Copy the Worker's address, for example `https://smartcircle-share.yourname.workers.dev`.
6. In `secrets.php` on the site, add it:

   ```php
   'app' => ['url' => 'https://smartcircle.gt.tc', 'share_url' => 'https://smartcircle-share.yourname.workers.dev'],
   ```

From then on every share button (home page, About, library, each handout) shares the Worker address.

**Check it:** paste a Worker link into WhatsApp (to yourself), or test it at
`https://developers.facebook.com/tools/debug/`. The card should show; tapping the link opens the real site.

**Your own domain later?** In the Worker's **Settings → Domains & Routes** you can add e.g. `share.yourdomain.mw`.

## Changing the card or the wording

- The card is `public/assets/brand/share-card.png`. The wording of each page's preview is in
  `share-worker.src.js` (`PAGES`).
- After a change run `php tools/cloudflare/build.php` and paste the new `share-worker.js` (step 3), or deploy
  with `npx wrangler deploy --config tools/cloudflare/wrangler.toml`.
