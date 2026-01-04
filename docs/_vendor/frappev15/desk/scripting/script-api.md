List of restricted commands that be called in Frappe Framework Server Script, Print Formats and Script Reports

**Note:** This is only applicable for in-app scripting. If you want more features, you will have to create an "Application" and write the event handlers inside Python Modules

### Python Modules

Following python modules are available

#### `json`

Python standard module `json`

### Formatting

#### \_ (Translate)

Translate a string

Example: `_("This is translatable")`

#### frappe.format

Format a value based on its datatype

Example: `frappe.format_value(value, dict(fieldtype='Currency'))`

#### frappe.date\_format

Format as default date format

#### frappe.format\_date

Returns date as "1st September 2019"

### Session

#### frappe.form\_dict

Form / request parameters

Example: Request parameter `/page?name="test"` can be accesssed as `frappe.form_dict.name`

#### frappe.request

Request object

#### frappe.response

Response object

#### frappe.session.user

Current user

#### frappe.session.csrf\_token

CSRF token of the current session

#### frappe.user

Current user

#### frappe.get\_fullname

Returns fullname of the current user

#### frappe.get\_gravatar

Gets the user display image from `frappe.utils.get_gravatar_url`

#### frappe.full\_name

Fullname of the current user

### Documents (ORM)

Document access and editing

Get metadata object

#### frappe.new\_doc

Create a new Document record.

#### frappe.get\_doc

Get Document. You can also save or execute any method exposed by the document.

Example: `frappe.get_doc("User", frappe.session.user)`

#### frappe.get\*last\*doc

Get the last Document of a particular DocType with a given set of filters. Defaults to last created.

Example: `frappe.get_last_doc("User", filters={"name": ("like", "%@apple.com")})`

#### frappe.get\*cached\*doc

Get Document (or cached)

#### frappe.get\*mapped\*doc

#### frappe.rename\_doc

#### frappe.delete\_doc

#### frappe.get\*system\*settings

Get system default settings

### Database

Database access API

#### frappe.db.get\_list

Get list of record filtered by current user's permissions

Example: `frappe.db.get_list("Customer")` will return list of customers

#### frappe.db.get\_all

Get list of all records

#### frappe.db.sql

Run a SELECT query

Example: `frappe.db.sql("select name from Customer where name like 'm%'")`

#### frappe.db.get\_value

Get a value from a record

Example: `frappe.db.get_value("User", frappe.session.user, "first_name")`

#### frappe.db.get\*single\*value

Get value from a single type document

Example: `frappe.db.get_single_value("System Settings", "default_currency")`

#### frappe.db.get\_default

Get default value

#### frappe.db.escape

Sanitize value for database queries to prevent SQL injection

#### frappe.db.set\_value

Set a value

#### frappe.db.exists

Checks for existence of a document. Returns `name` of the document if exists else `None`.

#### frappe.db.commit

Allow users to make explicit commits in server scripts like custom scheduler scripts.
Note: This won't work in any DocType Event server scripts

#### frappe.db.rollback

Allow users to rollback changes via server scripts.
Note: This won't work in any DocType Event server scripts

### Cache Methods

Available cache methods

#### frappe.cache.set\_value

Set a value in cache for a specific key

Example: `frappe.cache().set_value("test_key", "test_value")`

#### frappe.cache.get\_value

Get a value from cache for a specific key

Example: `frappe.cache().get_value("test_key")`

#### frappe.cache.hset

Set a value in cache for a specific key for a specific named record

Example: `frappe.cache().hset("sales_invoice", "test_key", "test_value")`

#### frappe.cache.hget

Get a value from cache for a specific key for a specific named record

Example: `frappe.cache().hget("sales_invoice", "test_key")`

### Query Builder

#### frappe.qb

Query builder API to run SELECT queries.

Example: `frappe.qb.from_("Task").select("*").run()`

### Utilities

Utility methods and functions

#### run\_script

Run a server script (return values in `frappe.flags`)

#### frappe.msgprint

Show a modal on the server side after as a part of the response.

Example: `frappe.msgprint("Hello")`

#### frappe.get\_hooks

Get application hooks

#### frappe.utils

Methods in frappe.utils

#### frappe.render\_template

Render a Jinja template

#### frappe.get\_url

Get url of the current site via `frappe.utils.get_url`

#### socketio\_port

Port for socketio

#### style.border\_color

Returns '#d1d8dd'

#### guess\_mimetype

Returns mimetypes.guess\_type

#### html2text

Encode HTML as text (markdown)

#### dev\_server

True if in developer mode

#### frappe.log\_error

Generate Error Log with a traceback

#### FrappeClient

Connect to a Frappe site using a requests session

### API

Make external API calls from Frappe.

#### frappe.make\*get\*request

Make a GET request.

Example: `frappe.make_get_request('https://example.com')`

#### frappe.make\*post\*request

Make a POST request.

Example: `frappe.make_post_request('https://example.com', data={'username: 'test'})`

#### frappe.make\*put\*request

Make a PUT request.

Example: `frappe.make_put_request('https://example.com', headers={'Auth': 'Bearer xyz'})`

### Email

#### frappe.sendmail

Send an email. [See docs](https://frappeframework.com/docs/v14/user/en/api/utils#frappesendmail)

Example: `frappe.sendmail(recipients=['test@example.com'], sender='sender@example.com', subject='My Subject', message='Hello

')`
