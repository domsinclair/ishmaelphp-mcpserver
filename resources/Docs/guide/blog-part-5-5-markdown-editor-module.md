# Blog Tutorial — Interlude 5.5: Reusable Markdown Editor Module (Full Guide)

In this interlude you will build a reusable Markdown editor as a standalone module that contributes only views (no database, no controllers). You will integrate it into the Blog Post create/edit forms without changing your services or controllers. The editor includes:
- A textarea with sensible defaults
- A collapsible Markdown help panel (cheat sheet)
- Optional live preview with CDN-based marked.js and an offline-safe fallback

This guide is intentionally comprehensive and copy‑pasteable so you can follow along even if you’re new to Ishmael.

Prerequisites:
- Completed Parts 1–5 (you have a Blog module with basic CRUD views)
- You can browse to /blog/posts/create and /blog/posts/{id}/edit

What you’ll build:
- Modules/MarkdownEditor module (UI-only)
- Integration into Blog Post forms, preserving the body field name

Outcomes:
- Authors get inline help and preview while writing
- No backend changes required because the form field name stays body

---

## 1) Create the module skeleton

Create the following file tree inside your app (or core example app), next to your Blog module:

```
Modules/
  MarkdownEditor/
    module.json
    Views/
      _help.php
      markdown-editor.php
```

Full contents for each file are provided below.

---

## 2) Module manifest (module.json)

Create Modules/MarkdownEditor/module.json:

```json
{
  "name": "MarkdownEditor",
  "description": "Reusable Markdown editor UI with inline help and optional preview.",
  "version": "0.1.0",
  "namespace": "Modules\\MarkdownEditor",
  "enabled": true,
  "env": ["local", "production"]
}
```

Notes
- If your app uses PHP manifests instead, you can create module.php that returns metadata. Either approach is fine if it matches your Module Discovery configuration. See Concepts: Module Discovery.

---

## 3) Help partial (Views/_help.php)

Create Modules/MarkdownEditor/Views/_help.php:

```php
<?php
// A compact Markdown cheat sheet shown inside the editor.
?>
<div class="md-help text-sm leading-6">
  <p class="font-medium mb-2">Markdown quick reference</p>
  <ul class="list-disc pl-5 space-y-1">
    <li><code>#</code> to <code>######</code> for headings</li>
    <li><code>**bold**</code>, <code>*italic*</code></li>
    <li><code>[link text](https://example.com)</code></li>
    <li>Lists: start lines with <code>-</code> or numbers like <code>1.</code></li>
    <li>Inline code: <code>`code`</code>, fenced blocks: <code>```php</code> ... <code>```</code></li>
  </ul>
  <p class="mt-2 text-xs text-zinc-600">Tip: You can write plain text; Markdown is optional.</p>
  <style>
    .md-help code { background: #f4f4f5; padding: 0 .25rem; border-radius: .25rem; }
  </style>
  </div>
```

This partial is display-only; it has no dependencies.

---

## 4) Editor partial (Views/markdown-editor.php)

Create Modules/MarkdownEditor/Views/markdown-editor.php with the following complete implementation. It accepts variables from the parent view and is safe to include multiple times on a page (IDs are suffixed).

Expected variables when including this partial
- name (string, required): form field name, e.g., 'body'
- label (string, optional): defaults to 'Body'
- value (string, optional): initial value
- preview (bool, optional): enable live preview (default true)

```php
<?php
/**
 * Reusable Markdown editor partial.
 * Variables expected:
 * - string $name (required)
 * - string $label (optional)
 * - string $value (optional)
 * - bool   $preview (optional, default true)
 */

$name    = isset($name) ? (string)$name : 'body';
$label   = isset($label) && $label !== '' ? (string)$label : 'Body';
$value   = isset($value) ? (string)$value : '';
$preview = array_key_exists('preview', get_defined_vars()) ? (bool)$preview : true;

// Generate a unique-ish ID suffix so multiple editors can coexist.
$id = 'md_' . substr(md5($name . microtime()), 0, 6);
?>

<div class="md-editor" id="<?= htmlspecialchars($id) ?>">
  <label for="<?= htmlspecialchars($id) ?>_ta" class="block mb-1 font-medium"><?= htmlspecialchars($label) ?></label>

  <div class="md-toolbar mb-2 flex gap-2 text-sm">
    <button type="button" class="md-btn md-toggle-help px-2 py-1 border rounded" aria-expanded="false">Help</button>
    <?php if ($preview): ?>
      <button type="button" class="md-btn md-toggle-preview px-2 py-1 border rounded" aria-expanded="false">Preview</button>
    <?php endif; ?>
  </div>

  <textarea id="<?= htmlspecialchars($id) ?>_ta" name="<?= htmlspecialchars($name) ?>" rows="12"
    class="w-full border rounded p-2 font-mono text-sm" spellcheck="true" placeholder="Write Markdown..."><?= htmlspecialchars($value) ?></textarea>

  <div class="md-panels mt-2">
    <div class="md-help-panel hidden border rounded p-3 bg-zinc-50">
      <?php
      $help = __DIR__ . '/_help.php';
      if (is_file($help)) { include $help; }
      ?>
    </div>
    <?php if ($preview): ?>
      <div class="md-preview-panel hidden border rounded p-3 bg-white">
        <div class="md-preview prose"></div>
      </div>
    <?php endif; ?>
  </div>

  <style>
    /* Lightweight, editor-scoped styles (no global leakage) */
    #<?= htmlspecialchars($id) ?> .hidden { display: none; }
    #<?= htmlspecialchars($id) ?> .prose { line-height: 1.6; }
    #<?= htmlspecialchars($id) ?> .prose pre { background: #0b1020; color: #e6edf3; padding: .75rem; border-radius: .375rem; overflow: auto; }
    #<?= htmlspecialchars($id) ?> .prose code { background: #f4f4f5; padding: 0 .25rem; border-radius: .25rem; }
  </style>

  <script>
    (function(){
      const root = document.getElementById('<?= addslashes($id) ?>');
      if (!root) return;

      const ta = root.querySelector('textarea');
      const helpBtn = root.querySelector('.md-toggle-help');
      const helpPanel = root.querySelector('.md-help-panel');
      const previewBtn = root.querySelector('.md-toggle-preview');
      const previewPanel = root.querySelector('.md-preview-panel');
      const previewDiv = root.querySelector('.md-preview');

      function toggle(el, btn){
        const isHidden = el.classList.contains('hidden');
        el.classList.toggle('hidden');
        if (btn) btn.setAttribute('aria-expanded', String(isHidden));
      }

      function escapeHtml(html){
        return html
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
      }

      // Very tiny fallback Markdown renderer (not full spec), safe-ish by default.
      function fallbackRender(md){
        const esc = escapeHtml(md);
        // headings
        let html = esc
          .replace(/^######\s+(.*)$/gm, '<h6>$1</h6>')
          .replace(/^#####\s+(.*)$/gm, '<h5>$1</h5>')
          .replace(/^####\s+(.*)$/gm, '<h4>$1</h4>')
          .replace(/^###\s+(.*)$/gm, '<h3>$1</h3>')
          .replace(/^##\s+(.*)$/gm, '<h2>$1</h2>')
          .replace(/^#\s+(.*)$/gm, '<h1>$1</h1>');
        // bold/italic
        html = html
          .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
          .replace(/\*(.+?)\*/g, '<em>$1</em>');
        // code spans
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        // links
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" rel="nofollow noopener" target="_blank">$1<\/a>');
        // unordered lists
        html = html.replace(/^(?:-\s+.+\n?)+/gm, match => {
          const items = match.trim().split(/\n/).map(li => '<li>' + li.replace(/^[-]\s+/, '') + '</li>').join('');
          return '<ul>' + items + '</ul>';
        });
        // paragraphs (very naive)
        html = html.replace(/^(?!<h\d|<ul|<pre|\s*$)(.+)$/gm, '<p>$1</p>');
        return html;
      }

      async function ensureMarked(){
        if (window.marked) return true;
        // Try to load from CDN once per page. If blocked, we fall back.
        if (document.getElementById('marked-cdn')) return false;
        const s = document.createElement('script');
        s.id = 'marked-cdn';
        s.src = 'https://cdn.jsdelivr.net/npm/marked/marked.min.js';
        s.async = true;
        document.head.appendChild(s);
        // Give it a short time; don’t block forever.
        await new Promise(r => setTimeout(r, 400));
        return !!window.marked;
      }

      function render(){
        if (!previewDiv) return;
        const md = ta.value || '';
        if (window.marked && typeof window.marked.parse === 'function') {
          // marked escapes by default when sanitization disabled; we trust only authoring view
          previewDiv.innerHTML = window.marked.parse(md, { mangle: false, headerIds: true });
        } else {
          previewDiv.innerHTML = fallbackRender(md);
        }
      }

      if (helpBtn && helpPanel) {
        helpBtn.addEventListener('click', () => toggle(helpPanel, helpBtn));
      }
      if (previewBtn && previewPanel) {
        previewBtn.addEventListener('click', async () => {
          toggle(previewPanel, previewBtn);
          if (!previewPanel.classList.contains('hidden')) {
            const ok = await ensureMarked();
            if (!ok) { /* fallback continues */ }
            render();
          }
        });
      }
      if (previewDiv) {
        ta.addEventListener('input', render);
      }
    })();
  </script>
</div>
```

Why this design?
- UI-only: no PHP classes or DB involved; works in any module.
- Safe by default: preview escapes HTML and allows only minimal formatting in the fallback.
- Progressive enhancement: if CDN is blocked, authors still get a basic preview.

---

## 5) Integrate with Blog Post forms

Open your Blog module form partial at Modules/Blog/Views/posts/_form.php and replace only the body textarea area. Keep the field name body so your controllers/services remain unchanged.

Before (simplified):

```php
<!-- ...other fields... -->
<label for="body">Body</label>
<textarea name="body" id="body" rows="12"><?php echo htmlspecialchars($post['body'] ?? ''); ?></textarea>
<!-- ...submit button... -->
```

After (include the editor partial):

```php
<?php
$bodyValue = $post['body'] ?? '';
$editor = __DIR__ . '/../../MarkdownEditor/Views/markdown-editor.php';
if (is_file($editor)) {
    $name = 'body';
    $label = 'Body (Markdown)';
    $value = $bodyValue;
    $preview = true; // set to false to disable live preview
    include $editor;
} else {
    // Fallback to original textarea if the editor module is missing
    ?>
    <label for="body">Body</label>
    <textarea name="body" id="body" rows="12" class="w-full border rounded p-2"><?php echo htmlspecialchars($bodyValue); ?></textarea>
    <?php
}
?>
```

No controller/service changes are needed because the field name remains body. Your store/update handlers continue to read $request->input('body') or $_POST['body'] as before.

---

## 6) Try it out

- Navigate to /blog/posts/create
- Click “Help” to toggle the cheat sheet
- Click “Preview” to toggle live preview, type into the textarea, confirm it updates
- Save a post and ensure your data persists as raw Markdown

Optional: open /blog/posts/{id}/edit and repeat the steps.

---

## 7) Rendering Markdown on public pages (later)

For authoring we preview on the client. For public pages, render on the server with a trusted library and sanitize output. Popular choices:
- league/commonmark
- parsedown (be sure to enable safe mode)

Guidelines
- Store raw Markdown in the database
- Convert to HTML on render and sanitize the result
- Never trust user-supplied HTML directly

---

## 8) Troubleshooting & FAQ

Q: I don’t see the editor, only a textarea.
- Check the include path for the partial: __DIR__ . '/../../MarkdownEditor/Views/markdown-editor.php'
- Ensure Modules/MarkdownEditor exists and module.json is present (if your app requires manifests to load views)

Q: The Preview button does nothing.
- CDN might be blocked; the fallback should still render headings/links/bold/italic/lists. Ensure JavaScript is enabled.
- Open the browser console for errors.

Q: Will this leak CSS globally?
- The editor inlines tiny, scoped CSS under a unique root ID to avoid leakage. For full styling, consider a shared stylesheet loaded in your layout.

Q: Can I reuse this in other modules?
- Yes. Include the partial the same way in any module’s form. The module is UI-only.

---

## 9) What you learned

- How to build a decoupled, UI-only module with view partials
- How to add a Markdown help panel and live (progressively enhanced) preview
- How to integrate the editor into existing forms without backend changes

Next: Proceed to Part 6: Pagination and Search, or later parts for styling/JS enhancements.
