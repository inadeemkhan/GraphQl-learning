# Magento 2 GraphQL — Products, CMS Page & Category Query

A single GraphQL request that reads **product**, **CMS page** and **category** data from Magento. Every
example below was executed against a live **Magento 2.4.6-p3** instance (Venia sample data, default store
view, USD) — the outputs are real.

| Item | Value |
| --- | --- |
| Endpoint | `POST {BASE_URL}/graphql` · header `Content-Type: application/json` |
| Root fields | `products`, `cmsPage`, `categories` |
| Custom module | `DevScripts_GraphQL` (`app/code/DevScripts/GraphQL`) — see [§6](#6-custom-method--devscripts_graphql) |
| Verified | 30 Sep 2026 |

**Contents:** [1 Query](#1-the-query) · [2 Field reference](#2-field-reference) ·
[3 Example response](#3-example-response) · [4 Running it](#4-running-it) ·
[5 Notes & caveats](#5-notes--caveats) · [6 Custom method](#6-custom-method--devscripts_graphql) ·
[7 Troubleshooting](#7-troubleshooting) · [8 Verification](#8-verification)

---

## 1. The query

```graphql
query Products {
    products(filter: { sku: { eq: "VVP01" } }, pageSize: 10) {
        id
        sku
        name
        qty
        status
        stock_status
    }
    cmsPage(id: 6) {
        content_heading
        identifier
        title
        url_key
    }
    categories(filters: { ids: { eq: "13" } }) {
        items {
            created_at
            is_anchor
            name
            path
            include_in_menu
            products {
                items {
                    name
                    price {
                        regularPrice {
                            amount {
                                currency
                                value
                            }
                        }
                    }
                    sku
                    updated_at
                    url_key
                }
                total_count
            }
        }
    }
}
```

In one round trip it returns: the product with SKU `VVP01` (quantity, status, stock status), CMS page
`6`, and category `13` together with the products it contains.

---

## 2. Field reference

### 2.1 `products` → type `Products` (a **single object**, not a list)

| Argument | Type | Notes |
| --- | --- | --- |
| `filter` | `ProductAttributeFilterInput` | e.g. `sku: { eq: "VVP01" }` |
| `sku`, `id` | `String` | Custom shortcuts (`sku` wins over `id`) |
| `pageSize`, `currentPage`, `sort`, `search` | — | Accepted for compatibility, ignored by the resolver |

| Field | Type | Notes |
| --- | --- | --- |
| `id` | `String` | Product entity ID |
| `sku`, `name` | `String` | SKU / product name |
| `qty` | `Float` | Stock quantity (`0.0` when no stock record) |
| `status` | `ProductStatus` | `ENABLED` / `DISABLED` |
| `stock_status` | `ProductStockStatus` | `IN_STOCK` / `OUT_OF_STOCK` |

`qty`, `status`, `ProductStatus` and the `sku`/`id` arguments are **not** core Magento fields — they come
from the custom module ([§6](#6-custom-method--devscripts_graphql)).

### 2.2 `cmsPage` → type `CmsPage`

Arguments: `id` (`Int`, **deprecated**) and `identifier` (`String`, preferred).

Selected fields: `identifier`, `url_key`, `title`, `content_heading` — also available: `content`,
`page_layout`, `meta_title`, `meta_description`, `meta_keywords`.

### 2.3 `categories` → type `CategoryResult`

Arguments: `filters` (`CategoryFilterInput`: `ids: { eq: "13" }`, plus `url_key`, `url_path`, `name`,
`parent_id`, `category_uid` …), `pageSize`, `currentPage`.

`items` are `CategoryTree` (implements `CategoryInterface`):

| Field | Type | Notes |
| --- | --- | --- |
| `created_at` | `String` | Creation timestamp |
| `is_anchor`, `include_in_menu` | `Int` | `0`/`1` — numeric, **not** boolean |
| `name` | `String` | Category name |
| `path` | `String` | Root-to-category IDs, e.g. `1/2/12/13` |
| `products` | `CategoryProducts` | `items`, `total_count`, `page_info` |

Nested product selections: `name`, `sku`, `updated_at`, `url_key`,
`price.regularPrice.amount.{currency,value}`.

> **Price caveat:** `price` is the deprecated legacy field and returns the *regular* price only. For the
> price the customer actually pays use
> `price_range { minimum_price { final_price { value currency } discount { amount_off percent_off } } }`.

---

## 3. Example response

Category items trimmed to the first product (the live call returned all 12, `total_count: 12`):

```json
{
    "data": {
        "products": { "id": "14", "sku": "VVP01", "name": "Venia Stylist Consultation", "qty": 1000, "status": "ENABLED", "stock_status": "IN_STOCK" },
        "cmsPage": { "content_heading": "", "identifier": "venia-new-home", "title": "Home Page - Venia", "url_key": "venia-new-home" },
        "categories": {
            "items": [
                {
                    "created_at": "2026-09-27 09:06:09",
                    "is_anchor": 1,
                    "name": "Pants & Shorts",
                    "path": "1/2/12/13",
                    "include_in_menu": 1,
                    "products": {
                        "items": [
                            {
                                "name": "Selena Pants",
                                "price": { "regularPrice": { "amount": { "currency": "USD", "value": 108 } } },
                                "sku": "VP01",
                                "updated_at": "2026-09-27 09:06:12",
                                "url_key": "selena-pants"
                            }
                        ],
                        "total_count": 12
                    }
                }
            ]
        }
    }
}
```

---

## 4. Running it

### cURL

```bash
curl -s -X POST 'https://magento2.4.6-p3.test/graphql' \
  -H 'Content-Type: application/json' \
  --data-binary @payload.json | jq .

# payload.json -> { "query": "<the query from §1 as one JSON string>" }
```

### Postman

No GraphQL-specific setup needed: `POST https://magento2.4.6-p3.test/graphql`, header
`Content-Type: application/json`, body `raw` → `JSON`.

![GraphQL query executed in Postman](https://raw.githubusercontent.com/inadeemkhan/GraphQl-learning/master/postman-image.png)

*The `products`, `cmsPage` and `categories` root fields resolved in one Postman request (screenshot from
the [GraphQl-learning](https://github.com/inadeemkhan/GraphQl-learning) repository).*

Ready-to-paste raw body (single-line form of §1):

```json
{ "query": "query Products { products(filter: { sku: { eq: \"VVP01\" } }, pageSize: 10) { id sku name qty status stock_status } cmsPage(id: 6) { content_heading identifier title url_key } categories(filters: { ids: { eq: \"13\" } }) { items { created_at is_anchor name path include_in_menu products { items { name price { regularPrice { amount { currency value } } } sku updated_at url_key } total_count } } } }" }
```

### Response envelope

HTTP `200` in every case — always check the `errors` key:

| Case | Shape |
| --- | --- |
| Success | `{ "data": { … } }` |
| e.g. unknown CMS page | `{ "errors": [ { "message": "…", "path": ["cmsPage"] } ], "data": { "cmsPage": null } }` |
| Unknown field | `{ "errors": [ { "message": "Cannot query field \"x\" on type \"Products\"." } ] }` |

---

## 5. Notes & caveats

- `products` returns **one object** (custom resolver), so `items` / `total_count` are `null` on it.
- Unknown SKU → all fields `null`, **no error**; treat `sku: null` as “not found”.
- No `sku`/`id` → the resolver silently returns the **first catalogue product**; always pass a SKU or ID.
- Lookups run in the **current store scope** (default store or the `Store` header).
- `cmsPage` with an unknown ID → entry in `errors[]` and `cmsPage: null`; `content_heading` may be `""`.
- A non-existent category is not an error: `items: []`, `total_count: 0`.
- Without `pageSize`, up to 20 categories are returned; the nested `products.items` list comes back in full.
- Need the same field twice? Use aliases:

  ```graphql
  { productA: products(filter: { sku: { eq: "VVP01" } }) { sku }
    productB: products(filter: { sku: { eq: "VP01" } }) { sku } }
  ```

  → `{"productA":{"sku":"VVP01"},"productB":{"sku":"VP01"}}`

---

## 6. Custom method — `DevScripts_GraphQL`

`app/code/DevScripts/GraphQL/etc/schema.graphqls` (`@doc` annotations omitted):

```graphql
type Query {
    products (
        sku: String,
        id: String,
        pageSize: Int = 20
    ) : Products @resolver(class: "DevScripts\\GraphQL\\Model\\Resolver\\Products") @cache(cacheIdentity: "DevScripts\\GraphQL\\Model\\Resolver\\Products\\Identity")
}

type Products {
    id : String
    name : String
    sku : String
    qty : Float
    status : ProductStatus
    stock_status : ProductStockStatus
}

enum ProductStatus { ENABLED DISABLED }
```

All four request forms below were verified:

```graphql
# a) by SKU — direct argument
{ products(sku: "VVP01") { id sku name qty status stock_status } }

# b) by SKU — standard filter input (used in §1)
{ products(filter: { sku: { eq: "VVP01" } }) { id sku name qty status stock_status } }

# c) by entity ID (String, or Int literal — both work)
{ products(id: "14") { id sku name } }

# d) no argument — first catalogue product
{ products { id sku } }
```

- **a / b** → `{"products":{"id":"14","sku":"VVP01","name":"Venia Stylist Consultation","qty":1000,"status":"ENABLED","stock_status":"IN_STOCK"}}`
- **c** → `{"products":{"id":"14","sku":"VVP01","name":"Venia Stylist Consultation"}}`
- **d** → `{"products":{"id":"1","sku":"UA-CB550F3"}}`
- unknown SKU (`products(sku: "NOPE")`) → `{"products":{"id":null,"sku":null,"name":null,"qty":null,"status":null,"stock_status":null}}`

### How it works

| File | Role |
| --- | --- |
| `Model/Resolver/Products.php` | `Query.products` resolver — forwards `$args` to the data provider |
| `Model/Resolver/DataProvider/Products.php` | product + stock lookup and mapping |
| `Model/Resolver/Products/Identity.php` | cache identity |

Lookup order: `sku` argument → `filter.sku.eq` → `id` → first product. The data provider returns an array
whose keys match the schema fields (`id`, `name`, `sku`, `qty`, `status`, `stock_status`); `qty` comes
from `StockStatusRepositoryInterface::get($id)->getQty()`, and `status` (`ENABLED`/`DISABLED`) and
`stock_status` (`IN_STOCK`/`OUT_OF_STOCK`) are mapped to the enums. Missing products raise
`NoSuchEntityException` internally, which is swallowed — hence the all-null response instead of an error.
The cache identity is `[config cache tag, config cache tag + '_' + id]`, so flush the config cache to
bypass cached results.

```bash
# enable the module
php bin/magento module:enable DevScripts_GraphQL
php bin/magento setup:upgrade
php bin/magento cache:flush
```

To expose another attribute, add the field to `type Products` plus the matching key to the data-provider
array, then flush the cache (see also `app/code/DevScripts/GraphQL/README.md`).

**Limitations**

- It **replaces** the core catalogue resolver: `items`, `total_count`, `page_info`, `aggregations` and
  `sort`/`search` filtering are declared (the types are merged) but return no data here.
- One product per request — `pageSize`, `currentPage`, `sort`, `search` are ignored.
- `Products.id` is a `String`; the core `ProductInterface.id` is an `Int`.

---

## 7. Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `Cannot query field "qty" on type "Products".` | `DevScripts_GraphQL` disabled or stale schema cache → `module:enable DevScripts_GraphQL && setup:upgrade && cache:flush` |
| `The CMS page with the "6" ID doesn't exist.` | Wrong ID or store view → query by `identifier` instead |
| `filter: { sku: { eq: VVP01 } }` fails | String values must be quoted: `{ eq: "VVP01" }` |
| Empty `categories.items` | Category disabled, not assigned to the store view, or wrong ID |
| `products` returns an unexpected product | No `sku`/`id` passed → first-product fallback |
| `price.regularPrice` differs from the storefront | Legacy field ignores price rules → use `price_range.minimum_price.final_price` |

---

## 8. Verification

| Check | Result |
| --- | --- |
| `products(filter: { sku: { eq: "VVP01" } }, pageSize: 10)` | `14 / VVP01 / Venia Stylist Consultation / 1000 / ENABLED / IN_STOCK` |
| `products(sku: "VVP01")` | as above, `__typename: Products` |
| `products(id: "14")` · `products(id: 14)` | `14 / VVP01 / Venia Stylist Consultation` |
| `products` (no argument) | first catalogue product: `1 / UA-CB550F3` |
| `products(sku: "NOPE")` | all fields `null`, no `errors` |
| `cmsPage(id: 6)` | `"" / venia-new-home / Home Page - Venia / venia-new-home` |
| `cmsPage(id: 99999)` | `errors[0].message = The CMS page with the "99999" ID doesn't exist.` |
| `categories(filters: { ids: { eq: "13" } })` | `Pants & Shorts`, `path 1/2/12/13`, `total_count: 12` |
| `categories(filters: { ids: { eq: "9999" } })` | `total_count: 0`, `items: []` |
| `price_range.minimum_price` | `108 / USD`, discount `0` |

Scope: Magento 2.4.6-p3 with Venia sample data, default store view, USD. SKU `VVP01`, CMS page `6` and
category `13` come from the sample data set — replace them with your own identifiers; quantities, prices
and timestamps depend on your data.

---

## 👨‍💻 Author

<p align="center">
  <img src="https://github.com/inadeemkhan.png" alt="Nadeem Khan" width="100" style="border-radius: 50%;"/>
  <br>
  <b>Nadeem Khan</b><br>
  🌐 <a href="https://inadeemkhan.github.io">Portfolio</a> | 📧 <a href="mailto:khannadeem243@gmail.com">khannadeem243@gmail.com</a>
</p>

<p align="center">
  <a href="https://github.com/inadeemkhan">
    <img src="https://img.shields.io/github/followers/inadeemkhan?style=social" alt="GitHub Follow"/>
  </a>
  <a href="https://inadeemkhan.github.io">
    <img src="https://img.shields.io/badge/Portfolio-Visit-blue" alt="Portfolio"/>
  </a>
</p>
