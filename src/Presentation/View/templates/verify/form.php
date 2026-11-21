<section class="section">
    <div class="container narrow">
        <div class="card">
            <h1>Verify a document</h1>
            <p>Enter the code printed at the bottom of a SMART Circle letter or certificate.</p>
            <form method="get" action="/verify" class="form">
                <label class="field">
                    <span class="field__label">Code</span>
                    <input type="text" name="code" autocapitalize="characters" spellcheck="false" placeholder="LABC2345" required>
                </label>
                <button type="submit" class="btn btn--block">Check</button>
            </form>
        </div>
    </div>
</section>
