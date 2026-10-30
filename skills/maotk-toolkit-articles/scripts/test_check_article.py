"""Offline behavioral checks for the read-only article preflight."""
import unittest
from check_article import inspect_article

ROOT = '<div class="maotk-ui-kit mtk-article-reading">{}</div>'


class ArticlePreflightTests(unittest.TestCase):
    def test_escaped_code_is_not_executed_markup(self):
        body = '<div class="mtk-code-wrap"><pre class="wp-block-zibllblock-enlighter"><code>&lt;script&gt;demo()&lt;/script&gt;</code></pre></div>'
        self.assertTrue(inspect_article(ROOT.format(body))["ok"])

    def test_inline_resource_and_event_are_rejected(self):
        for body in ('<style>p{color:red}</style>', '<script src="/custom.js"></script>', '<p style="color:red">x</p>', '<a onclick="x()">x</a>', '<link rel="stylesheet" href="/custom.css">'):
            with self.subTest(body=body):
                self.assertFalse(inspect_article(ROOT.format(body))["ok"])

    def test_native_inert_links_keep_their_logic(self):
        self.assertTrue(inspect_article(ROOT.format('<a class="post-tab-toggle" href="javascript:;" tab-id="0">A</a>'))["ok"])
        self.assertFalse(inspect_article(ROOT.format('<a href="javascript:alert(1)">A</a>'))["ok"])

    def test_frontend_snapshot_is_not_an_authoring_template(self):
        for extra in ('data-mao-tk-ui-code="1"', 'data-mtk-reading-ready="1"', 'data-mao-tk-note-visual="1"'):
            self.assertFalse(inspect_article(f'<div class="maotk-ui-kit" {extra}>x</div>')["ok"])

    def test_unwrapped_code_fails_but_fragment_can_be_composed(self):
        body = '<div class="mtk-code-wrap"><pre class="wp-block-zibllblock-enlighter">x</pre></div>'
        self.assertFalse(inspect_article(body)["ok"])
        self.assertTrue(inspect_article(body, fragment=True)["ok"])
        self.assertFalse(inspect_article('<div class="mtk-article-reading">缺少根类</div>')["ok"])

    def test_block_balance_and_object_ids(self):
        self.assertFalse(inspect_article('<!-- wp:group --><!-- /wp:paragraph -->')["ok"])
        self.assertFalse(inspect_article(ROOT.format('[postsbox post_id="{{POST_ID}}"]'))["ok"])
        self.assertFalse(inspect_article(ROOT.format('[productbox id="0"]'))["ok"])

    def test_duplicate_id_and_missing_faq_region_fail(self):
        self.assertFalse(inspect_article(ROOT.format('<p id="a">A</p><p id="a">B</p>'))["ok"])
        self.assertFalse(inspect_article(ROOT.format('<button class="mtk-faq-trigger" id="q" aria-expanded="true" aria-controls="missing">Q</button>'))["ok"])

    def test_supported_note_config_is_not_css(self):
        block = '<!-- wp:maotk/note {"type":"info","maoTkNoteVisual":{"light":"#285fa2","iconSize":20,"showIcon":false}} --><aside>说明</aside><!-- /wp:maotk/note -->'
        self.assertTrue(inspect_article(block)["ok"])
        self.assertFalse(inspect_article(block.replace('"iconSize":20', '"iconSize":200'))["ok"])


if __name__ == "__main__":
    unittest.main()
