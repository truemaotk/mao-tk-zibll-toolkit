#!/usr/bin/env python3
"""Read-only preflight for stored MaoTK article source; standard library only.

This is not a WordPress block validator, URL checker or authorization test.
"""
from __future__ import annotations

import argparse
from html.parser import HTMLParser
import json
from pathlib import Path
import re

PLACEHOLDER = re.compile(r"\{\{[A-Z][A-Z0-9_]*\}\}")
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}
RUNTIME = {"data-mao-tk-note-visual", "data-mtk-reading-ready", "data-mtk-open", "data-copied", "data-mtk-copy-busy", "data-mtk-link-ready", "data-mtk-link-preview", "data-mao-tk-ui-label"}
VISUAL_FIELDS = {"light", "dark", "badgePosition", "iconPosition", "iconKey", "iconSize", "showIcon", "titleColor"}


class ArticleParser(HTMLParser):
    def __init__(self, fragment: bool):
        super().__init__(convert_charrefs=True)
        self.fragment = fragment
        self.errors: list[str] = []
        self.warnings: list[str] = []
        self.stack: list[tuple[str, set[str]]] = []
        self.blocks: list[str] = []
        self.ids: dict[str, set[str]] = {}
        self.faq_links: list[tuple[str, str]] = []
        self.counts = {"roots": 0, "reading_roots": 0, "images": 0, "code_blocks": 0, "notes": 0, "faq_items": 0}

    def error(self, text):
        self.errors.append(f"line {self.getpos()[0]}: {text}")

    def warn(self, text):
        self.warnings.append(f"line {self.getpos()[0]}: {text}")

    def in_class(self, class_name):
        return any(class_name in classes for _, classes in self.stack)

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        classes = set((a.get("class") or "").split())
        root = "maotk-ui-kit" in classes
        reading = root and "mtk-article-reading" in classes
        if "mtk-article-reading" in classes and not root:
            self.error("mtk-article-reading 与 maotk-ui-kit 必须在同一个根组上。")
        if root:
            self.counts["roots"] += 1
            if self.in_class("maotk-ui-kit"):
                self.error("不要嵌套 maotk-ui-kit 根组；合并组件片段。")
        if reading:
            self.counts["reading_roots"] += 1
        if tag in {"style", "script"}:
            self.error(f"正文不能嵌入 <{tag}>；调用插件资源。")
        if tag == "link" and ("stylesheet" in (a.get("rel") or "").lower() or (a.get("as") or "").lower() in {"style", "script"}):
            self.error("正文不能引入样式表或脚本预加载。")
        for key, value in attrs:
            if key == "style" or re.fullmatch(r"on[a-z]+", key):
                self.error(f"禁止内联样式/事件属性：{key}")
            if key.startswith("data-mao-tk-ui-") or key in RUNTIME:
                self.error(f"前台运行时属性不能保存到正文：{key}")
            if value and PLACEHOLDER.search(value):
                self.error(f"未替换模板占位符：{key}")
            if key in {"href", "src", "xlink:href"} and value and value.strip().lower().startswith("javascript:"):
                if value.strip().lower() not in {"javascript:;", "javascript:void(0)", "javascript:void(0);"}:
                    self.error("不允许可执行 javascript: URL；原生空操作入口除外。")
        if "id" in a:
            identifier = a["id"] or ""
            if not identifier:
                self.error("空DOM id。")
            if identifier in self.ids:
                self.error(f"重复DOM id：{identifier}")
            self.ids[identifier] = classes
            if re.fullmatch(r"wznav_\d+", identifier):
                self.error("wznav_* 由主题生成，不在文章手写。")
        if tag == "h1":
            self.error("正文不重复H1；文章标题使用编辑器字段。")
        in_root = root or self.in_class("maotk-ui-kit")
        in_reading = reading or self.in_class("mtk-article-reading")
        for component in {"mtk-code-wrap", "mtk-native-tabs", "mtk-native-postsbox"} & classes:
            if not in_root and not self.fragment:
                self.error(f"{component} 必须位于 maotk-ui-kit 根组内。")
        if tag == "pre" and "wp-block-zibllblock-enlighter" in classes:
            self.counts["code_blocks"] += 1
            if not self.in_class("mtk-code-wrap"):
                self.error("原生Enlighter缺少 mtk-code-wrap 外层。")
        if "wp-block-image" in classes:
            self.counts["images"] += 1
            if not in_reading and not self.fragment:
                self.error("原生Image需要位于阅读根组中才能统一图片边界。")
        if "mtk-faq-item" in classes:
            self.counts["faq_items"] += 1
            if "data-mtk-faq" not in a:
                self.error("FAQ项缺少 data-mtk-faq 结构标记。")
            if not self.in_class("mtk-faqs"):
                self.error("FAQ项缺少 mtk-faqs 分组。")
        if ("mtk-faqs" in classes or "data-mtk-copy" in a) and not in_reading and not self.fragment:
            self.error("FAQ/变量复制需要 mtk-article-reading 根组。")
        if "mtk-faq-trigger" in classes:
            if tag != "button" or a.get("aria-expanded") != "true":
                self.error("FAQ保存为button且初始aria-expanded=true，保证无JS时可读。")
            if not a.get("id") or not a.get("aria-controls"):
                self.error("FAQ按钮缺少唯一id或aria-controls。")
            else:
                self.faq_links.append((a["id"], a["aria-controls"]))
        if "mtk-faq-panel" in classes:
            if "hidden" in a or "inert" in a or "aria-hidden" in a:
                self.error("FAQ源码不保存hidden/inert/aria-hidden瞬时状态。")
            if a.get("id") and a.get("aria-labelledby"):
                self.faq_links.append((a["id"], a["aria-labelledby"]))
            else:
                self.error("FAQ面板缺少id或aria-labelledby。")
        if tag == "img" and self.in_class("wp-block-image") and "alt" not in a:
            self.warn("文章图片缺少ALT属性；装饰图才使用空ALT。")
        if tag not in VOID:
            self.stack.append((tag, classes))

    def handle_endtag(self, tag):
        for i in range(len(self.stack) - 1, -1, -1):
            if self.stack[i][0] == tag:
                del self.stack[i:]
                return

    def handle_data(self, data):
        if PLACEHOLDER.search(data):
            self.error("正文或示例中仍有未替换的模板占位符。")
        for name, identifier in re.findall(r'\[(postsbox|productbox)\s+[^\]]*?\b(?:post_id|id)=["\']([^"\']+)["\']', data):
            if not identifier.isdigit() or int(identifier) < 1:
                self.error(f"{name} 必须使用真实正整数对象ID。")

    def handle_comment(self, data):
        token = data.strip()
        match = re.match(r"(/?)wp:([a-z0-9/-]+)(.*)$", token, re.S)
        if not match:
            return
        closing, name, rest = match.groups()
        if closing:
            if not self.blocks or self.blocks[-1] != name:
                self.error(f"Gutenberg关闭标记不匹配：{name}")
            else:
                self.blocks.pop()
            return
        self_closing = rest.rstrip().endswith("/")
        if not self_closing:
            self.blocks.append(name)
        payload = rest.rstrip().removesuffix("/").strip() if self_closing else rest.strip()
        if not payload:
            return
        if PLACEHOLDER.search(payload):
            self.error(f"{name} 区块属性中有未替换占位符。")
            return
        try:
            attrs = json.loads(payload)
        except json.JSONDecodeError:
            self.error(f"{name} 区块属性不是有效JSON。")
            return
        if not isinstance(attrs, dict):
            self.error(f"{name} 区块属性必须是对象。")
            return
        if "style" in attrs:
            self.error("不把区块自定义style配置写入文章；使用Toolkit外观。")
        if name == "image" and "id" in attrs and (type(attrs["id"]) is not int or attrs["id"] < 1):
            self.error("Image附件id必须是真实正整数。")
        if name != "maotk/note":
            return
        self.counts["notes"] += 1
        if attrs.get("type", "info") not in {"info", "primary", "success", "warning", "danger"}:
            self.error("未知提示框type。")
        visual = attrs.get("maoTkNoteVisual", {})
        if not isinstance(visual, dict):
            self.error("maoTkNoteVisual必须是稀疏对象。")
            return
        for key, value in visual.items():
            valid = key in VISUAL_FIELDS
            if key in {"light", "dark"}:
                valid = isinstance(value, str) and bool(re.fullmatch(r"#[0-9a-fA-F]{6}", value))
            elif key == "iconSize":
                valid = type(value) is int and 12 <= value <= 28
            elif key == "showIcon":
                valid = type(value) is bool
            elif key == "badgePosition":
                valid = value in {"left", "center", "right"} if isinstance(value, str) else False
            elif key == "iconPosition":
                valid = value in {"before", "after"} if isinstance(value, str) else False
            elif key == "titleColor":
                valid = value in {"type", "neutral"} if isinstance(value, str) else False
            elif key == "iconKey":
                valid = isinstance(value, str) and bool(re.fullmatch(r"[a-z][a-z0-9-]{0,47}", value))
            if not valid:
                self.error(f"无效提示框配置：{key}")

    def finish(self):
        if self.blocks:
            self.errors.append("Gutenberg未关闭区块：" + ", ".join(self.blocks))
        for source, target in self.faq_links:
            if target not in self.ids:
                self.errors.append(f"FAQ关联目标不存在：{source} -> {target}")
        if not self.fragment and not self.counts["reading_roots"]:
            self.warnings.append("未发现阅读根组；若仅交付独立提示框/原生业务块可忽略，否则核对正文包装。")
        return {"ok": not self.errors, "errors": self.errors, "warnings": self.warnings, "counts": self.counts,
                "scope": "stored source only; not WordPress editor, URL, payment, clipboard or browser validation"}


def inspect_article(source: str, fragment: bool = False):
    parser = ArticleParser(fragment)
    parser.feed(source)
    parser.close()
    return parser.finish()


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    cli.add_argument("source", type=Path)
    cli.add_argument("--fragment", action="store_true", help="Validate an unwrapped component fragment, not a complete article")
    cli.add_argument("--json", action="store_true")
    args = cli.parse_args()
    result = inspect_article(args.source.read_text(encoding="utf-8-sig"), args.fragment)
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print("PASS" if result["ok"] else "FAIL")
        for error in result["errors"]:
            print("ERROR:", error)
        for warning in result["warnings"]:
            print("WARNING:", warning)
        print(json.dumps(result["counts"], ensure_ascii=False))
    return 0 if result["ok"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
