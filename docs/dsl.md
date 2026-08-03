# Contract DSL

Every contract file returns an array with `syntax => v1` and a service map. Endpoint helpers are
`get`, `post`, `put`, `patch`, `delete`, and the method-explicit `endpoint`.

A request and a response are each described one of two ways, independently of one another: a
`rules()` set, or the name of an application-owned readonly DTO class. One endpoint may take rules
and answer a DTO.

## Contract and endpoint metadata

A contract file accepts `syntax`, `description`, `envelope` and `services`. `description` becomes
the OpenAPI tag description for every service in that file. `envelope()` describes successful JSON
responses and defaults to `{code: 0, msg: "successful", data: ...}`:

```php
use function Lumnd\PlatoApiContract\Dsl\envelope;
use function Lumnd\PlatoApiContract\Dsl\get;

return [
    'syntax' => 'v1',
    'description' => 'User API',
    'envelope' => envelope(
        statusField: 'status',
        successValue: 0,
        messageField: 'message',
        successMessage: 'ok',
        dataField: 'result',
    ),
    'services' => [
        'user' => get(
            '/user/show/{id}',
            ShowUserRequest::class,
            UserResponse::class,
            handler: 'show',
            auth: 'required',
            summary: 'Show a user',
            status: 200,
            description: 'Returns one user by ID.',
            tags: ['Users'],
            deprecated: false,
            operationId: 'users.show',
        ),
    ],
];
```

The helper arguments after request and response are:

| Argument | Meaning |
| --- | --- |
| `handler` | generated Logic/action name; optional for Plato paths and useful to a custom route convention |
| `auth` | `required`, `optional` or `none`; defaults to `required` |
| `summary` | OpenAPI operation summary |
| `status` | successful HTTP response status; defaults to `200` |
| `description` | OpenAPI operation description |
| `tags` | OpenAPI tags; defaults to the service name |
| `deprecated` | OpenAPI deprecation flag |
| `operationId` | explicit unique OpenAPI operation id; otherwise `{service}.{action}` |

`endpoint()` takes the HTTP method as its first argument; the convenience helpers fix it to `GET`,
`POST`, `PUT`, `PATCH` or `DELETE`. Instead of `summary` and `description`, an immediately attached
endpoint PHPDoc may use `@title` and `@desc` (or `@description`). Named arguments take precedence:

```php
/**
 * @title Show a user
 * @desc Returns one user by ID.
 */
get('/user/show/{id}', ShowUserRequest::class, UserResponse::class)
```

## Rule sets

`rules()` maps a field path to its rules, the way a Laravel FormRequest does.

```php
post(
    '/address/create',
    rules([
        'name'       => ['required', 'string', 'min:1', 'max:50', 'desc:Recipient name'],
        'phone'      => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
        'detail'     => ['required', 'string', 'min:1', 'max:200'],
        'is_default' => ['nullable', 'boolean'],
    ]),
    rules([
        'id'         => ['integer'],
        'name'       => ['string'],
        'created_at' => ['string', 'nullable'],
    ]),
)
```

Logic then receives and returns plain arrays, and its generated skeleton carries the matching
PHPStan array shapes:

```php
/**
 * @param array{name: string, phone: string, detail: string, is_default: bool|null} $request
 * @return array{id: int, name: string, created_at: string|null}
 */
```

### Vocabulary

| Rule | Effect |
| --- | --- |
| `required` | must be sent |
| `nullable` | may be absent, and reaches Logic as `null` |
| `optional` | response only: the key may be absent from the answer |
| `string` `integer` `numeric` `boolean` `array` `object` | the type; `string` when none is given |
| `min:N` `max:N` | length on a string, value on a number |
| `size:N` `between:A,B` | exact length, and `min` plus `max` |
| `regex:/…/` | a PCRE, delimiters included |
| `email` `url` `date` | format, and the matching validator rule |
| `in:a,b,c` | the admitted values |
| `default:V` | what an absent field becomes |
| `desc:TEXT` | the OpenAPI description |
| `from:…` | `query`, `json`, `form`, `header`, `cookie`, `file` or `segment`, when the default is wrong |

An unknown rule is a contract error, not something handed to the validator: `requried` fails lint
rather than refusing every request at runtime.

A field's rules are a list, or the one pipe separated string a FormRequest would write:
`'mode' => 'required|string|regex:/^(brief|full)$/'`. The pattern of a `regex:` is read as the PCRE
literal it is, so the `|` inside it separates alternatives rather than rules.

### Presence has to be stated

Every request field must declare `required`, `nullable`, or a `default:`. A field that declares none
of them is refused with `rules.presence_undeclared`, because it is exactly the field whose absence
would otherwise be settled by accident.

That decision is what the generated controller projects. Every declared field reaches Logic holding
the value sent, the declared default, or null - never a missing key. A response field is present
unless it says `optional`, and `nullable` means present but possibly null.

A boolean cannot be `required`: `validate` reads `false` as nothing sent, so the two are
indistinguishable. Give it a `default:` or make it `nullable`. A DTO says the same thing with a
`bool` that has no constructor default, and is refused with `dto.boolean_required`.

What a boolean admits is checked: `1`, `0`, `true`, `false`, `on`, `off`, `yes`, `no`, in either
case, and a JSON boolean. Anything else is a 422 rather than a silent `false`, because `fasle` is a
caller's mistake and not a decision Logic should be handed.

### Nesting

A dotted path describes an object, and `*` describes the elements of an array.

```php
rules([
    'buyer.name'  => ['required', 'string', 'max:50'],
    'buyer.age'   => ['nullable', 'integer', 'min:0'],
    'tags'        => ['nullable', 'array'],
    'tags.*'      => ['string', 'max:20'],
    'items'       => ['required', 'array'],
    'items.*.sku' => ['required', 'string'],
])
```

An array must declare its elements with `.*`, and an object its properties. A parent named only
through its children is required when a child is.

`buyer.name` becomes `buyer[name]` for the validator and a nested object in OpenAPI. An element is
`tags[*]`, which the generated controller resolves against the request before validating it:
`items.*.sku` is checked as `items[0][sku]`, `items[1][sku]`, one name per element sent, and an
element that breaks a rule is reported under its own index. A constraint that only reaches inside an
array is therefore enforced, and errors say which element was wrong.

`required` on the array itself is about the array: it has to be sent, and it has to hold something.

### The declared container is checked

A declared type says what kind of value arrives, and that is refused rather than reshaped. A field
declared as one value is a 422 when a list arrives, instead of having its rules run against the
elements and then being projected to `''`; a field declared as an array is a 422 when anything else
arrives, or when the JSON names its elements (`{"0": ...}`) rather than listing them, because the
projection and the documented `list<T>` both mean a list.

A field declared as an object is a 422 when a value that is not one arrives - `"buyer": "nope"` or
`"buyer": ["nope"]` - rather than being projected to `[]`. Its required properties would have caught
most of that on their own, but an object whose properties are all `nullable` or carry a `default:`
demands nothing of the caller and so used to accept anything at all. `{}` is accepted, and so is
`[]`: PHP decodes both to the same empty array, so nothing tells them apart by the time a rule runs.

The rules that ask this are `validate`'s own `scalar`, `list` and `map`, decided while the value is
still whole and before the rules are taken down to the elements. Errors read the same as any other:
one per field, under the field's own name, from the same message set. A `from: file` field is the
exception: what an upload looks like is plato's business, not the contract's.

### What a refused request answers

`422` with the field errors, unless the project registered an exception class -- see
[configuration](configuration.md). A successful one is `resp::response()`, plato's own envelope,
which also carries a `timestamp` the contract does not describe.

### Sources

A path placeholder is read from the segment; anything else from the query string for GET and DELETE,
and from the JSON body otherwise. `from:` overrides that. A path parameter must be `required`, and
every placeholder needs a rule of its own.

The bundled Plato platform accepts `/{service}/{action}[/{parameter}...]`; all segments after the
action must be path parameters, not literals. `:id` and `{id}` are equivalent contract spellings and
normalize to `{id}` in OpenAPI. The service must match the `services` key, and an explicit `handler`
must normalize to the action segment. A framework-neutral Template Pack may use
`StandardRouteConvention`, which accepts arbitrary normalized paths and uses an explicit `handler`
or the last literal path segment as its action.

## DTO mode

Pass class names instead of rule sets to work with typed objects:

```php
post('/user/create', CreateUserRequest::class, UserResponse::class)
```

DTO classes must be `final readonly`, with a public constructor whose parameters promote public,
typed properties. Supported property types are `string`, `int`, `float`, `bool`, `array`, backed
enums and nested DTO classes; only nullable unions such as `string|null` are accepted. DTO classes
must be autoloadable when lint or generation runs.

A constructor default makes a request property optional. Nullability and presence are independent:
`?string $name` without a default is still required, while `?string $name = null` is optional and
uses null when absent. A required `bool` is rejected because PlatoPHP validation cannot distinguish
a submitted `false` from an absent value; give it a constructor default.

`#[ApiField]` adds `source`, `format`, `minLength`, `maxLength`, `minimum`, `maximum`, `enum`,
`description` and the required `items` type for an array. Property PHPDoc may use `@desc` (or
`@description`) and, on requests, `@must true|false`; `@must false` still requires a constructor
default. Logic receives and returns the declared DTO types, and the controller builds the request DTO
from the input it read.

```php
final readonly class CreateOrderRequest
{
    /** @param list<LineRequest> $lines */
    public function __construct(
        /**
         * @desc Lines included in the order.
         * @must true
         */
        #[ApiField(items: LineRequest::class)]
        public array $lines,
        #[ApiField(source: 'header', description: 'Caller request ID')]
        public ?string $request_id = null,
    ) {
    }
}
```

A nested request DTO says the same things a nested rule path does: a property with a constructor
default is not demanded of the caller, and the properties of `#[ApiField(items: LineReq::class)]`
elements are checked element by element. A response DTO is written rather than read, so every one of
its promoted properties is present regardless of its constructor default. Response projection uses
only the declared DTO structure; extra array fields or custom `JsonSerializable` output do not leak
into the response.

`?array $tags = null` reaches Logic as null when nothing was sent, because that is what the DTO
itself declares. A non-nullable array without a constructor default is required: when it is absent or
empty, validation answers 422 and Logic is not called. If it is optional with an array default, that
declared default is used.

## Authentication

`auth` is `required` (the default), `optional`, or `none`, and is generated into the controller's
`$actions` for PlatoPHP to route on.

- `required` - the framework insists on an identity and answers the request itself when there is
  none. Documented with bearer security and a 401.
- `optional` - the authentication callback runs and may answer nobody; the operation runs either
  way. Documented as admitting both the empty scheme and bearer.
- `none` - the callback is not called and `plato::$auth` stays null.

Anything past "is somebody signed in" is the application's own question. The contract has no
vocabulary for roles or permissions, and generates no second guard of its own.
