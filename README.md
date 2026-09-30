# Magento 2 GraphQL — Products, CMS Page & Category Query

Documentation for the composite GraphQL query that reads **product**, **CMS page** and **category**
data in a single request against the Magento 2 GraphQL endpoint (`/graphql`).

The query was executed and verified against a live **Magento 2.4.6-p3 (Community Edition)** instance
with Venia sample data. Every request/response pair shown below is real output, not a mock.

| Item | Value |
| --- | --- |
| Magento version | 2.4.6-p3 (project `2.4.6-p3`, `magento/product-community-edition` 2.4.6-p15) |
| Endpoint | `POST {BASE_URL}/graphql` |
| Content type | `application/json` |
| Operation name | `Products` (an arbitrary, user-defined name for the operation) |
| Root fields | `products`, `cmsPage`, `categories` |
| Custom module involved | `DevScripts_GraphQL` / `devscripts/module-graphql` (`app/code/DevScripts/GraphQL`) — see [§8](#8-custom-method--fetching-products-via-graphql-devscripts_graphql) |
| Verified on | 30 Sep 2026 |

---

## Table of contents

- [1. The query](#1-the-query)
- [2. Environment & prerequisites](#2-environment--prerequisites)
- [3. Field reference](#3-field-reference)
  - [3.1 `products`](#31-products)
  - [3.2 `cmsPage`](#32-cmspage)
  - [3.3 `categories`](#33-categories)
  - [3.4 Nested product fields under `categories`](#34-nested-product-fields-under-categories)
- [4. Example response](#4-example-response)
- [5. How to run it](#5-how-to-run-it)
- [6. Behaviour, edge cases & caveats](#6-behaviour-edge-cases--caveats)
- [7. Troubleshooting](#7-troubleshooting)
- [8. Custom method — fetching products via GraphQL (`DevScripts_GraphQL`)](#8-custom-method--fetching-products-via-graphql-devscripts_graphql)
  - [8.1 Schema declared by the module](#81-schema-declared-by-the-module)
  - [8.2 Request & response examples](#82-request--response-examples)
  - [8.3 How the resolver works](#83-how-the-resolver-works)
  - [8.4 Cache identity](#84-cache-identity)
  - [8.5 Enabling the module](#85-enabling-the-module)
  - [8.6 Extending the method](#86-extending-the-method)
  - [8.7 Limitations](#87-limitations)
- [9. Implementation notes](#9-implementation-notes)
- [10. Verification log](#10-verification-log)

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

What it returns in one round trip:

1. **`products`** — a single product identified by SKU `VVP01`, with quantity, status and stock status.
2. **`cmsPage`** — the CMS page with ID `6` (heading, identifier, title, URL key).
3. **`categories`** — category ID `13`, its metadata, and the products it contains (name, regular price, SKU, last update, URL key) plus the total product count.

## 2. Environment & prerequisites

| Requirement | Details |
| --- | --- |
| Magento edition/version | 2.4.6-p3 (Open Source) with GraphQL enabled |
| Module | `DevScripts_GraphQL` must be enabled (`bin/magento module:status DevScripts_GraphQL`) — it registers the custom `products` resolver and the `qty`, `status`, `ProductStatus` fields |
| Sample data | Venia sample data (provides SKU `VVP01`, CMS page ID `6`, category ID `13`) |
| Cache | `bin/magento cache:flush` after enabling the module or editing `etc/schema.graphqls` |
| Auth | Not required for these fields (all are public catalogue/CMS data) |
| Optional headers | `Store: <store_view_code>` and `Content-Currency: <currency_code>` |

> **Note** — `store` can also be passed as a request header. All results in this document were
> produced on the default store view with the USD currency.

---

## 3. Field reference

### 3.1 `products`

**Argument reference**

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `filter` | `ProductAttributeFilterInput` | – | Attribute filter. `sku` uses `FilterEqualTypeInput` (`eq` / `in`). |
| `pageSize` | `Int` | `20` | Accepted for API compatibility. |
| `currentPage` | `Int` | `1` | Accepted for API compatibility. |
| `sort` | `ProductAttributeSortInput` | – | Accepted for API compatibility. |
| `sku` | `String` | – | Shortcut for `filter.sku.eq` (custom argument). |
| `id` | `String` | – | Look a product up by entity ID (custom argument). |

**Returned fields** — type `Products`

| Field | Type | Description |
| --- | --- | --- |
| `id` | `String` | Product entity ID, returned as a string. |
| `sku` | `String` | Stock Keeping Unit. |
| `name` | `String` | Product name. |
| `qty` | `Float` | Quantity available in stock (`0.0` when the stock record does not exist). |
| `status` | `ProductStatus` | `ENABLED` or `DISABLED`. |
| `stock_status` | `ProductStockStatus` | `IN_STOCK` or `OUT_OF_STOCK`. |

**Enums**

| Enum | Values |
| --- | --- |
| `ProductStatus` | `ENABLED`, `DISABLED` |
| `ProductStockStatus` | `IN_STOCK`, `OUT_OF_STOCK` |

`qty`, `status`, `ProductStatus`, and the `id`/`sku` shortcut arguments are **not** part of stock
Magento core GraphQL — they are added by the `DevScripts_GraphQL` module, documented in full in
[§8 Custom method](#8-custom-method--fetching-products-via-graphql-devscripts_graphql) (see also
[§9 Implementation notes](#9-implementation-notes)).

### 3.2 `cmsPage`

**Argument reference**

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `Int` | The ID of the CMS page. **Deprecated** — use `identifier` instead. Still functional in 2.4.6-p3. |
| `identifier` | `String` | The URL key/identifier of the CMS page (recommended argument). |

Exactly one of `id` or `identifier` is normally supplied.

**Returned fields** — type `CmsPage`

| Field | Type | Description |
| --- | --- | --- |
| `content_heading` | `String` | Heading displayed at the top of the page (empty string when not set). |
| `identifier` | `String` | Page identifier (URL key). |
| `title` | `String` | Page title, shown in breadcrumbs and the browser tab. |
| `url_key` | `String` | URL key — usually the same value as `identifier`. |

Other selectable fields on `CmsPage`: `content`, `page_layout`, `meta_title`, `meta_description`, `meta_keywords`.

### 3.3 `categories`

**Argument reference**

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `filters` | `CategoryFilterInput` | – | Supports `ids` (`FilterEqualTypeInput`: `eq`/`in`), `url_key`, `url_path`, `name`, `parent_id`, `category_uid`, `parent_category_uid`. |
| `pageSize` | `Int` | `20` | Maximum number of categories returned. |
| `currentPage` | `Int` | `1` | Page of the result set to return. |

`ids.eq` is declared as `String`, so the category ID is quoted (`"13"`). An unquoted integer literal
also resolves (GraphQL coerces it to a string), but quoting is the documented form.

**Returned fields** — type `CategoryResult` → `items` are `CategoryTree` (implements `CategoryInterface`)

| Field | Type | Description |
| --- | --- | --- |
| `created_at` | `String` | Category creation timestamp (`YYYY-MM-DD HH:MM:SS`). |
| `is_anchor` | `Int` | `1` = anchor category (products from child categories are included), `0` = not anchor. Values are numeric, **not** boolean. |
| `name` | `String` | Category name. |
| `path` | `String` | Root-to-category path of IDs, e.g. `1/2/12/13`. |
| `include_in_menu` | `Int` | `1` = shown in the storefront menu, `0` = hidden. |
| `products` | `CategoryProducts` | The products assigned to the category (see §3.4). |

The `CategoryTree`/`CategoryInterface` type exposes many more fields (`id`, `uid`, `url_key`,
`url_path`, `description`, `image`, `level`, `position`, `children_count`, `meta_*`, `children`,
`cms_block`, …); only the requested ones are returned.

### 3.4 Nested product fields under `categories`

`categories.items[].products` is of type `CategoryProducts`:

| Field | Type | Description |
| --- | --- | --- |
| `items` | `[ProductInterface]` | Products in the category. |
| `total_count` | `Int` | Total number of products in the category (ignores `pageSize`). |
| `page_info` | `SearchResultPageInfo` | `current_page`, `page_size`, `total_pages` (not selected in this query). |

Fields selected on each product (`ProductInterface`):

| Field | Type | Description |
| --- | --- | --- |
| `name` | `String` | Product name. |
| `price.regularPrice.amount.currency` | `CurrencyEnum` | Currency code of the regular price (e.g. `USD`). |
| `price.regularPrice.amount.value` | `Float` | Regular (list) price value. |
| `sku` | `String` | Stock Keeping Unit. |
| `updated_at` | `String` | Last modification timestamp. |
| `url_key` | `String` | Product URL key (usable in the storefront `/{url_key}.html` URL). |

> **Price caveat** — `ProductInterface.price` (`ProductPrices.regularPrice` → `Price.amount` → `Money`)
> is a **legacy/deprecated** field and returns the *regular* price, ignoring catalog/special price
> rules. For the actual selling price use the modern price range, e.g.
>
> ```graphql
> price_range {
>     minimum_price {
>         final_price { value currency }
>         regular_price { value currency }
>         discount { amount_off percent_off }
>     }
> }
> ```

## 4. Example response

Verified response (category `products.items` trimmed to the first two entries for brevity — the live
call returned all 12 products):

```json
{
    "data": {
        "products": {
            "id": "14",
            "sku": "VVP01",
            "name": "Venia Stylist Consultation",
            "qty": 1000,
            "status": "ENABLED",
            "stock_status": "IN_STOCK"
        },
        "cmsPage": {
            "content_heading": "",
            "identifier": "venia-new-home",
            "title": "Home Page - Venia",
            "url_key": "venia-new-home"
        },
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
                                "price": {
                                    "regularPrice": {
                                        "amount": {
                                            "currency": "USD",
                                            "value": 108
                                        }
                                    }
                                },
                                "sku": "VP01",
                                "updated_at": "2026-09-27 09:06:12",
                                "url_key": "selena-pants"
                            },
                            {
                                "name": "Gloria Palazzo Pants",
                                "price": {
                                    "regularPrice": {
                                        "amount": {
                                            "currency": "USD",
                                            "value": 88
                                        }
                                    }
                                },
                                "sku": "VP02",
                                "updated_at": "2026-09-27 09:06:12",
                                "url_key": "gloria-palazzo-pants"
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

Because the query does not select `page_info` for `categories`, Magento returns the whole result set
(up to the default `pageSize` of 20). Add `pageSize`/`currentPage` when a category contains many
products.

---

## 5. How to run it

### 5.1 cURL

```bash
curl -s -X POST 'https://magento2.4.6-p3.test/graphql' \
  -H 'Content-Type: application/json' \
  -d '{
    "query": "query Products { products(filter: { sku: { eq: \"VVP01\" } }, pageSize: 10) { id sku name qty status stock_status } cmsPage(id: 6) { content_heading identifier title url_key } categories(filters: { ids: { eq: \"13\" } }) { items { created_at is_anchor name path include_in_menu products { items { name price { regularPrice { amount { currency value } } } sku updated_at url_key } total_count } } } }"
  }' | jq .
```

Or keep the payload in a file so the query stays readable:

```bash
# payload.json
# { "query": "<paste the query from §1 here, escaped as a single JSON string>" }

curl -s -X POST 'https://magento2.4.6-p3.test/graphql' \
  -H 'Content-Type: application/json' \
  --data-binary @payload.json | jq .
```

### 5.2 Magento admin / GraphiQL

The **GraphiQL** IDE is served by GraphQL modules that expose a browser UI (for example
`magento/module-graph-ql`'s `/graphiql` route in developer setups). Paste the query from §1 and run it
against `/graphql`.

### 5.3 Response envelope

| Case | Shape |
| --- | --- |
| Success | `{ "data": { ... } }` |
| Partial failure (e.g. unknown CMS page ID) | `{ "errors": [ { "message": "...", "path": ["cmsPage"] } ], "data": { "cmsPage": null } }` |
| Validation error (unknown field/argument) | `{ "errors": [ { "message": "Cannot query field \"x\" on type \"Products\"." } ] }` |

The HTTP status is `200` in all of the above cases — always inspect the `errors` key.

## 6. Behaviour, edge cases & caveats

All of the following were reproduced on the live instance.

**`products`**

- Returns a **single object**, not a list. The root `products` field is served by the custom
  `DevScripts\GraphQL\Model\Resolver\Products` resolver, which resolves one product per request.
  Selecting `items` or `total_count` therefore returns `null`:

  ```json
  { "data": { "products": { "total_count": null, "id": "14", "sku": "VVP01", "items": null } } }
  ```

- **Unknown SKU ⇒ all-null object, no error** (HTTP 200, empty `errors` omitted):

  ```json
  { "data": { "products": { "id": null, "sku": null, "name": null, "qty": null, "status": null, "stock_status": null } } }
  ```

  Treat `products.sku === null` as “not found”.

- **No `sku` and no `id` ⇒ fallback to the first product in the catalogue** (catalogue order is not
  guaranteed and is not the same as `sort`). Always pass a SKU/ID.

- `sku` can be given directly (`products(sku: "VVP01")`) or through the standard filter input
  (`filter: { sku: { eq: "VVP01" } }`); both forms are handled by the resolver.

- `pageSize` (and `currentPage`/`sort`/`search`) are accepted by the schema but ignored by the
  resolver — only one product is returned.

- `qty` is the stock quantity as a float; when no stock record exists it is reported as `0.0`.

- `id` is a `String` here (custom schema). In the core `ProductInterface` the field `id` is an `Int`
  and `uid` is the canonical identifier — do not confuse the two when combining this field with
  nested `ProductInterface` selections.

**`cmsPage`**

- The `id` argument is **deprecated** (reason: *“Use `identifier` instead.”*). Prefer
  `cmsPage(identifier: "venia-new-home")`, which was verified to work.
- A CMS page that does not exist produces a GraphQL error and `null` data:

  ```json
  {
    "errors": [ { "message": "The CMS page with the \"99999\" ID doesn't exist.", "path": ["cmsPage"] } ],
    "data": { "cmsPage": null }
  }
  ```

- `content_heading` can legitimately be an empty string when the page has no heading.

**`categories`**

- A category that does not exist is **not** an error — you get an empty list:

  ```json
  { "data": { "categories": { "total_count": 0, "items": [] } } }
  ```

- `is_anchor` and `include_in_menu` are `Int` (`0`/`1`), so compare against numbers, not `true`/`false`.
- Without `pageSize`, up to 20 categories are returned (default). Without `page_info`, no pagination
  metadata is exposed; the nested `products.items` list is returned in full for the category.
- The nested `products.total_count` reports all products in the category (12 for category `13`),
  independent of `pageSize`.
- `categories.items[].products.items[].price.regularPrice` is the **deprecated legacy price path** and
  returns the regular price only. Use `price_range.minimum_price.final_price` for the price the
  customer pays (catalog price rules, special prices and discounts included).
- Multiple root fields can be combined in one operation because all three are queries. If you need the
  same field twice with different arguments, use aliases:

  ```graphql
  query {
      productA: products(filter: { sku: { eq: "VVP01" } }) { sku }
      productB: products(filter: { sku: { eq: "VP01" } }) { sku }
  }
  ```

  ```json
  { "data": { "productA": { "sku": "VVP01" }, "productB": { "sku": "VP01" } } }
  ```

---

## 7. Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| `Cannot query field "qty" on type "Products".` | `DevScripts_GraphQL` is disabled or the schema cache is stale. | `bin/magento module:enable DevScripts_GraphQL && bin/magento setup:upgrade && bin/magento cache:flush` |
| `Cannot query field "status" on type "Products".` | Same as above. | Same as above. |
| `The CMS page with the "6" ID doesn't exist.` | Page ID does not exist (or is assigned to another store view). | Query by `identifier` or check **Content → Pages** in the admin. |
| `"Variable \"$...\" of type ... used in position ..."` / literal-type errors | Malformed filter syntax, e.g. `filter: { sku: { eq: VVP01 } }` (missing quotes). | Quote string values: `{ eq: "VVP01" }`. |
| Empty `categories.items` | Category is disabled, not assigned to the store view, or the ID is wrong. | Verify the category in the admin and the `Store` header/store view scope. |
| `products` returns a different product than expected | No `sku`/`id` argument was passed, so the resolver fell back to the first product. | Always pass `filter.sku.eq` or `sku`. |
| `price.regularPrice.amount.value` differs from the storefront price | Legacy price field ignores catalog price rules/special prices. | Use `price_range.minimum_price.final_price`. |
| Changes to `schema.graphqls` have no effect | Compiled/stale GraphQL schema cache. | `bin/magento cache:flush` (and `setup:upgrade` in production after deploy). |

## 8. Custom method — fetching products via GraphQL (`DevScripts_GraphQL`)

The `products` field used in §1 is **not** the stock Magento field on this instance: it is extended by
the custom module **`DevScripts_GraphQL`** (`devscripts/module-graphql`), which adds stock quantity,
product status and two shortcut arguments. This section documents that custom method on its own, so it
can be used independently of the composite query.

### 8.1 Schema declared by the module

`app/code/DevScripts/GraphQL/etc/schema.graphqls` — `@doc(description: …)` annotations are omitted below
for readability; the resolver and cache-identity class names are unchanged:

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

| Custom argument | Type | Description |
| --- | --- | --- |
| `sku` | `String` | Shortcut for `filter.sku.eq`; takes precedence over `id`. |
| `id` | `String` | Product entity ID (`getById`). |
| `pageSize` | `Int` (default `20`) | Declared for compatibility; the resolver returns one product per request. |

| Custom field | Type | Description |
| --- | --- | --- |
| `qty` | `Float` | Stock quantity (`0.0` when no stock record exists). |
| `status` | `ProductStatus` | `ENABLED` / `DISABLED`. |
| `stock_status` | `ProductStockStatus` | `IN_STOCK` / `OUT_OF_STOCK` (core enum from `Magento_CatalogInventoryGraphQl`). |

`ProductStockStatus` is the core enum, so it does **not** need to be redeclared; only `ProductStatus`
is new.

### 8.2 Request & response examples

All four forms below were executed against the live endpoint; outputs are verbatim.

**a) By SKU (direct argument)**

```graphql
{
    products(sku: "VVP01") {
        __typename
        id
        sku
        name
        qty
        status
        stock_status
    }
}
```

```json
{"data":{"products":{"__typename":"Products","id":"14","sku":"VVP01","name":"Venia Stylist Consultation","qty":1000,"status":"ENABLED","stock_status":"IN_STOCK"}}}
```

**b) By SKU through the standard filter input** (what §1 uses)

```graphql
{
    products(filter: { sku: { eq: "VVP01" } }, pageSize: 10) {
        id
        sku
        name
        qty
        status
        stock_status
    }
}
```

```json
{"data":{"products":{"id":"14","sku":"VVP01","name":"Venia Stylist Consultation","qty":1000,"status":"ENABLED","stock_status":"IN_STOCK"}}}
```

**c) By entity ID**

```graphql
{
    products(id: "14") {
        id
        sku
        name
    }
}
```

```json
{"data":{"products":{"id":"14","sku":"VVP01","name":"Venia Stylist Consultation"}}}
```

An integer literal (`products(id: 14)`) also resolves — the custom `id` argument is declared as
`String`, and GraphQL’s string coercion accepts `IntValue`.

**d) No argument — first-product fallback**

```graphql
{
    products {
        id
        sku
        name
        qty
        status
        stock_status
    }
}
```

```json
{"data":{"products":{"id":"1","sku":"UA-CB550F3","name":"Geovision UA-CB550F3 5 Megapixel Super Low Lux Full Color IR Bullet Camera with 3.6mm Lens","qty":1000,"status":"ENABLED","stock_status":"IN_STOCK"}}}
```

A SKU that cannot be loaded in the current store scope returns nulls without an error:

```json
{"data":{"products":{"id":null,"sku":null,"name":null,"qty":null,"status":null,"stock_status":null}}}
```

### 8.3 How the resolver works

| File | Responsibility |
| --- | --- |
| `Model/Resolver/Products.php` | `ResolverInterface` implementation for `Query.products`; forwards the raw `$args` to the data provider. |
| `Model/Resolver/DataProvider/Products.php` | Resolves the requested product (by SKU or ID) and maps it to the `Products` value array. |
| `Model/Resolver/Products/Identity.php` | Cache identity for the stitched schema (`IdentityInterface`). |

```php
// Model/Resolver/Products.php
public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
{
    return $this->productsDataProvider->getProducts($args ?? []);
}
```

Lookup precedence implemented in `getProducts()` / `extractSku()`:

1. `sku` argument (`products(sku: "VVP01")`)
2. `filter.sku.eq` (`products(filter: { sku: { eq: "VVP01" } })`), as a plain string or `eq` object
3. `id` argument → `ProductRepositoryInterface::getById()`
4. nothing supplied → first product of `ProductRepositoryInterface::getList()` (page size 1)

Product and stock data are mapped as follows (`formatProduct()`):

```php
return [
    'id'           => (string) $product->getId(),
    'name'         => $product->getName(),
    'sku'          => $product->getSku(),
    'qty'          => $stockStatus !== null ? (float) $stockStatus->getQty() : 0.0,
    'status'       => (int) $product->getStatus() === Status::STATUS_ENABLED ? 'ENABLED' : 'DISABLED',
    'stock_status' => $this->resolveStockStatus($stockStatus), // IN_STOCK | OUT_OF_STOCK
];
```

The array keys must match the schema field names exactly — GraphQL reads each selected field straight
from the returned array, so no per-field resolvers are required. Both lookups are wrapped in
`try { … } catch (NoSuchEntityException $e) { return []; }`, which is why an unknown SKU produces an
object with null fields instead of an error.

### 8.4 Cache identity

```graphql
@cache(cacheIdentity: "DevScripts\\GraphQL\\Model\\Resolver\\Products\\Identity")
```

`Identity::getIdentities()` returns an empty array when the resolved `id` is empty, otherwise:

```php
[$this->cacheTag, sprintf('%s_%s', $this->cacheTag, $resolvedData['id'])]  // $cacheTag = Magento\Framework\App\Config::CACHE_TAG
```

Practical consequence: the identity is derived from `id` and the **config** cache tag only. Stock or
attribute changes that do not flush the config cache may therefore be served from the cached GraphQL
result — run `bin/magento cache:flush` when checking up-to-date quantities.

### 8.5 Enabling the module

```bash
php bin/magento module:enable DevScripts_GraphQL
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Verify the installation:

```bash
php bin/magento module:status DevScripts_GraphQL      # -> Module is enabled
grep -n "DevScripts_GraphQL" app/etc/config.php       # -> 'DevScripts_GraphQL' => 1

# Confirm the custom fields are exposed by the effective schema
curl -s -X POST 'https://magento2.4.6-p3.test/graphql' -H 'Content-Type: application/json' \
  -d '{"query":"{ __type(name: \"Products\") { fields { name } } }"}' | jq .
# -> id, name, sku, qty, status, stock_status (+ core items, page_info, total_count, …)
```

### 8.6 Extending the method

Because the fields are read from the array returned by the data provider, adding a field is a
three-step change (example: expose the product weight):

1. Add the field to `app/code/DevScripts/GraphQL/etc/schema.graphqls`:

   ```graphql
   type Products {
       id : String
       name : String
       sku : String
       qty : Float
       status : ProductStatus
       stock_status : ProductStockStatus
       weight : Float @doc(description: "The weight of the product.")
   }
   ```

2. Add the matching key to the array in `Model/Resolver/DataProvider/Products.php`:

   ```php
   'weight' => (float) $product->getWeight(),
   ```

3. Flush the schema/cache so the stitched schema is rebuilt:

   ```bash
   php bin/magento cache:flush
   ```

To expose a *new* root field instead, add another entry under `type Query` with its own
`@resolver(class: …)` and implement that resolver class in `Model/Resolver/`.

### 8.7 Limitations

- The custom resolver **replaces** the core `Magento\CatalogGraphQl\Model\Resolver\Products` resolver
  for this endpoint. Core list semantics — `items`, `total_count`, `page_info`, `aggregations`,
  `sort`/`search` filtering — are declared (because the types are merged) but return `null`/no data
  through this field on this instance.
- One product per request: `pageSize`, `currentPage`, `sort` and `search` are accepted but ignored.
- Lookups run against the **current store scope** (default store or the `Store` request header); a SKU
  that is not available in that scope returns an all-null object instead of an error.
- `Products.id` is a `String` (custom schema), while the core `ProductInterface.id` is an `Int`. Cast
  accordingly when mixing selections.
- The schema uses the reserved-looking type name `Products`, which is merged with the core type of the
  same name; renaming the custom type would avoid the merge but requires updating the resolver’s
  `return` type in the `.graphqls` file too.

---

## 9. Implementation notes

### Schema sources

| Field | Provided by | Schema file |
| --- | --- | --- |
| `products` (query) | Custom resolver `DevScripts\GraphQL\Model\Resolver\Products` (overrides/merges with the core catalog field) | `app/code/DevScripts/GraphQL/etc/schema.graphqls` |
| `Products.id / sku / name / qty / status / stock_status`, `enum ProductStatus` | `DevScripts_GraphQL` | `app/code/DevScripts/GraphQL/etc/schema.graphqls` |
| `cmsPage` (`CmsPage`) | `Magento_CmsGraphQl` | `vendor/magento/module-cms-graph-ql/etc/schema.graphqls` |
| `categories`, `CategoryTree`, `CategoryInterface`, `CategoryProducts` | `Magento_CatalogGraphQl` | `vendor/magento/module-catalog-graph-ql/etc/schema.graphqls` |
| `ProductInterface.stock_status` | `Magento_CatalogInventoryGraphQl` | `vendor/magento/module-catalog-inventory-graph-ql/etc/schema.graphqls` |
| `ProductInterface.price` / `price_range` | `Magento_CatalogGraphQl` | `vendor/magento/module-catalog-graph-ql/etc/schema.graphqls` |

### Custom module layout (`app/code/DevScripts/GraphQL`)

```text
app/code/DevScripts/GraphQL/
├── etc/
│   ├── module.xml
│   └── schema.graphqls                          # Query.products + type Products + enum ProductStatus
├── Model/Resolver/
│   ├── Products.php                             # Query.products resolver
│   ├── Products/Identity.php                    # cache identity
│   └── DataProvider/Products.php                # loads product + stock data
├── registration.php
└── composer.json
```

The custom schema block that makes the `qty` / `status` fields possible:

```graphql
type Products {
    id  : String
    name  : String
    sku  : String
    qty : Float
    status : ProductStatus
    stock_status : ProductStockStatus
}

enum ProductStatus { ENABLED DISABLED }
```

Because the core `Magento_CatalogGraphQl` module also declares a `Products` type and a `products`
query field, Magento's schema stitcher merges both declarations: the resulting `Products` type exposes
both the core fields (`items`, `page_info`, `total_count`, `aggregations`, `sort_fields`, `filters`,
`suggestions`) and the custom fields (`id`, `name`, `sku`, `qty`, `status`, `stock_status`), and
`Query.products` accepts `search`, `filter`, `pageSize`, `currentPage`, `sort`, `sku` and `id`
arguments. The custom resolver wins and is the one that executes.

The data provider maps Magento data to GraphQL values as follows:

| GraphQL field | Source |
| --- | --- |
| `id` | `Magento\Catalog\Api\Data\ProductInterface::getId()` (cast to string) |
| `name` | `ProductInterface::getName()` |
| `sku` | `ProductInterface::getSku()` |
| `qty` | `Magento\CatalogInventory\Api\StockStatusRepositoryInterface::get($productId)->getQty()` (`0.0` if the stock record is missing) |
| `status` | `ProductInterface::getStatus()` → `ENABLED` / `DISABLED` |
| `stock_status` | `StockStatusInterface::getStockStatus()` → `IN_STOCK` / `OUT_OF_STOCK` |

### Regenerating schema information

If you need to re-check the effective schema on an instance:

```bash
curl -s -X POST 'https://magento2.4.6-p3.test/graphql' -H 'Content-Type: application/json' \
  -d '{"query":"{ __type(name: \"Products\") { fields { name type { name kind ofType { name } } } } }"}' | jq .
```

---

## 10. Verification log

| Check | Command (abridged) | Result |
| --- | --- | --- |
| Products by SKU | `products(filter: { sku: { eq: "VVP01" } }, pageSize: 10) { id sku name qty status stock_status }` | `14 / VVP01 / Venia Stylist Consultation / 1000 / ENABLED / IN_STOCK` |
| CMS page by ID | `cmsPage(id: 6) { content_heading identifier title url_key }` | `"" / venia-new-home / Home Page - Venia / venia-new-home` |
| Category by ID | `categories(filters: { ids: { eq: "13" } })` | `Pants & Shorts`, `path 1/2/12/13`, 12 products (`total_count: 12`) |
| Unknown SKU | `filter: { sku: { eq: "NOPE-123" } }` | all fields `null`, no `errors` |
| Unknown CMS ID | `cmsPage(id: 99999)` | `errors[0].message = The CMS page with the "99999" ID doesn't exist.` |
| Unknown category ID | `ids: { eq: "9999" }` | `total_count: 0`, `items: []` |
| Modern price range | `price_range { minimum_price { final_price { value currency } regular_price { value currency } discount { amount_off percent_off } } }` | `108 / USD`, discount `0` |
| Custom method — direct `sku` argument | `products(sku: "VVP01") { __typename id sku name qty status stock_status }` | `Products / 14 / VVP01 / Venia Stylist Consultation / 1000 / ENABLED / IN_STOCK` |
| Custom method — `id` argument | `products(id: "14") { id sku name }` and `products(id: 14) { id sku }` | both return `14 / VVP01 / Venia Stylist Consultation` |
| Custom method — no argument | `products { id sku name qty status stock_status }` | first catalogue product: `1 / UA-CB550F3 / … / 1000 / ENABLED / IN_STOCK` |
| Custom method — unknown SKU | `products(sku: "NOPE")` | all fields `null`, no `errors` |
| Custom method — schema exposure | `__type(name: "Products") { fields { name } }` | fields include `id`, `name`, `sku`, `qty`, `status`, `stock_status` |
| Module state | `app/etc/config.php` | `'DevScripts_GraphQL' => 1` |

**Scope of verification** — Magento 2.4.6-p3 with Venia sample data, default store view, USD. The
sample SKU (`VVP01`), CMS page ID (`6`) and category ID (`13`) come from the sample data set; replace
them with identifiers from your own catalogue. Values such as `qty`, prices and timestamps depend on
your data.

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

