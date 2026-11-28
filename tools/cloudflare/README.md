# Share Worker: link previews for SMART Circle

**The problem.** InfinityFree checks each first visit with a small JavaScript test. The robots that WhatsApp,
Facebook, X, Telegram and LinkedIn send to build a link preview cannot run it, so a link straight to
`smartcircle.gt.tc` shows as a bare link, with no picture and no title.

**The fix.** Share buttons on the site point at this free Cloudflare Worker instead:
preview robots get the title, a description and the SMART Circle share card; people are sent straight on to
the same page on the real site. The Worker never contacts the site, so it never meets the JavaScript test.

> **Important:** only put `share_url` in `secrets.php` **after** the Worker works (step "Check it" below).
> While `share_url` is empty, share buttons share the normal site address, which always works.

There are two ways to install it. **Way A is easiest on a phone or iPad.**

## Way A: paste the small Worker (5 KB)

1. Open **dash.cloudflare.com** and sign in (a free account is enough).
2. **Workers & Pages → Create → Create Worker** (choose "Start with Hello World" if asked).
   Name it `smartcircle-share` and press **Deploy**.
3. Press **Edit code**. In the editor, select everything in `worker.js` and delete it.
4. On GitHub, open [`share-worker-lite.js`](share-worker-lite.js), press the **Copy raw file** button
   (two overlapping squares, top right of the file), and paste it into the empty editor.
5. Press **Deploy** (top right). Cloudflare shows the Worker's address, for example
   `https://smartcircle-share.yourname.workers.dev`.

The small Worker uses the copy of the share card stored on GitHub, so nothing else is needed.

## Way B: let GitHub install it (no copying)

1. In Cloudflare: **My Profile → API Tokens → Create Token → "Edit Cloudflare Workers" template → Continue → Create**.
   Copy the token (it is shown once).
2. In Cloudflare **Workers & Pages**, copy your **Account ID** (on the right of the page).
3. On GitHub: repository **Settings → Secrets and variables → Actions → New repository secret**:
   - `CLOUDFLARE_API_TOKEN` = the token
   - `CLOUDFLARE_ACCOUNT_ID` = the account ID
4. GitHub **Actions** tab → **Install the share Worker on Cloudflare** → **Run workflow**.
   When it turns green, the log shows the Worker's address (`https://smartcircle-share.<you>.workers.dev`).

This installs the full Worker (the card inside it). Run it again after changing the card or the wording.

## Check it, then switch the site over

1. Open the Worker's address in your browser: it should jump straight to the SMART Circle home page.
2. Open `https://developers.facebook.com/tools/debug/`, paste the Worker's address, press **Debug**:
   you should see the card with "SMART Circle: free lessons that show why it matters".
3. Only now add it to `secrets.php` on the site:

   ```php
   'app' => ['url' => 'https://smartcircle.gt.tc', 'share_url' => 'https://smartcircle-share.yourname.workers.dev'],
   ```

4. Share the home page on WhatsApp (to yourself). WhatsApp remembers previews, so try a link you have not shared before.

**If the Worker's address shows "Hello World"**, the new code was not deployed: open Edit code again, check the
editor holds the SMART Circle code, and press Deploy.

## Changing the card or the wording

The card is `public/assets/brand/share-card.png`; each page's wording is in `share-worker.src.js` (`PAGES`).
After a change run `php tools/cloudflare/build.php`, then paste again (Way A) or re-run the workflow (Way B).
