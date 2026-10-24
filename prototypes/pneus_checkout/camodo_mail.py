"""Strict extraction of the observed French B2C HTML format, without network IO.

Input must be decoded HTML from a privately authenticated mail worker. This
module does NOT authenticate a sender, associate a customer/session, recheck
stock, create checkout, or follow the supplier cart link. Unknown formats fail
closed. Only the observed single-product format is currently supported.
"""
from dataclasses import dataclass, field
from html.parser import HTMLParser
import re
from urllib.parse import urlsplit, parse_qs


@dataclass
class Node:
    tag: str
    attrs: dict = field(default_factory=dict)
    children: list = field(default_factory=list)

    def text(self):
        return re.sub(r"\s+", " ", " ".join(
            c if isinstance(c, str) else c.text() for c in self.children)).strip()

    def descendants(self, tag):
        for child in self.children:
            if isinstance(child, Node):
                if child.tag == tag:
                    yield child
                yield from child.descendants(tag)


class Tree(HTMLParser):
    def __init__(self, html):
        super().__init__(convert_charrefs=True)
        self.root = Node("root")
        self.stack = [self.root]
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        n = Node(tag, dict(attrs))
        self.stack[-1].children.append(n)
        if tag not in {"br", "hr", "img", "meta", "link", "input", "wbr", "source"}:
            self.stack.append(n)

    def handle_endtag(self, tag):
        for i in range(len(self.stack)-1, 0, -1):
            if self.stack[i].tag == tag:
                del self.stack[i:]
                break

    def handle_data(self, data):
        self.stack[-1].children.append(data)


def cents(value):
    value = re.sub(r"[\s\u00a0\u202f]", "", value)
    match = re.fullmatch(r"([0-9]{1,7}),([0-9]{2})€", value)
    if not match:
        raise ValueError("invalid_eur_amount")
    return int(match[1])*100 + int(match[2])


def quantity(value):
    match = re.fullmatch(r"([0-9]{1,2}),00", value.strip())
    if not match or not 1 <= int(match[1]) <= 24:
        raise ValueError("invalid_quantity")
    return int(match[1])


def parse_selection(html):
    if not isinstance(html, str) or len(html.encode()) > 1_000_000:
        raise ValueError("invalid_html")
    tree = Tree(html).root
    rows = []
    for row in tree.descendants("tr"):
        cells = [c.text() for c in row.children
                 if isinstance(c, Node) and c.tag in {"td", "th"}]
        if cells:
            rows.append(cells)
    # These explicit group labels prevent interpreting PA HT as retail TTC.
    if ["", "PA HT", "PV TTC"] not in rows:
        raise ValueError("unknown_price_layout")
    expected = ["Article(s)", "Qté", "Prix uni.", "Total", "TVA", "Prix uni.", "Total"]
    if expected not in rows:
        raise ValueError("unknown_columns")
    items = [r for r in rows if len(r) == 7 and
             re.search(r"\b[0-9]{3}/[0-9]{2}\s+R\s*[0-9]{2}\b", r[0])]
    if len(items) != 1:
        raise ValueError("single_product_required")
    item = items[0]
    title = re.split(r"Base de calcul HT|Consommation de carburant", item[0], maxsplit=1)[0].strip()
    qty = quantity(item[1])
    unit, total = cents(item[5]), cents(item[6])
    if not 0 < unit <= 10_000_000 or total != qty*unit:
        raise ValueError("retail_total_mismatch")
    totals = [cents(r[1]) for r in rows if len(r) == 2 and r[0] == "Prix total TTC"]
    if totals != [total]:
        raise ValueError("quote_total_mismatch")
    links = [a for a in tree.descendants("a") if a.text() == "Ajouter au panier"]
    if len(links) != 1:
        raise ValueError("supplier_reference_required")
    url = urlsplit(links[0].attrs.get("href", ""))
    if (url.scheme != "https" or url.netloc != "s-h-o-p24.pneus100.fr"
            or url.path != "/shop/wk.html" or url.fragment):
        raise ValueError("unknown_supplier_link")
    params = parse_qs(url.query, strict_parsing=True)
    if (set(params) != {"PID", "Menge"} or len(params["PID"]) != 1
            or len(params["Menge"]) != 1
            or not re.fullmatch(r"[A-Za-z0-9_-]{1,128}", params["PID"][0])
            or params["Menge"][0] != str(qty)):
        raise ValueError("invalid_supplier_reference")
    return {"title": title, "reference": params["PID"][0], "quantity": qty,
            "mail_unit_ttc_cents": unit, "mail_total_ttc_cents": total,
            "supplier_recheck_required": True, "payment_enabled": False}
