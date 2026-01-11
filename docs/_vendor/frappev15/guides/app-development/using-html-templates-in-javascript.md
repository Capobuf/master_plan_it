Often while building javascript interfaces, there is a need to render DOM as an HTML template. Frappe Framework uses John Resig's Microtemplate script to render HTML templates in the Desk application.

> Note 1: In Frappe we use the Jinja-like `{% raw %}{%{% endraw %}` tags to embed code rather than the standard `<%`

> Note 2: Never use single quotes `'` inside the HTML template.

To render a template,

1. Create a template `html` file in your app. e.g. `address_list.html`
2. Add it to `build.json` for your app (you can include it in `frappe.min.js` or your own javascript file).
3. To render it in your app, use `frappe.render(frappe.templates.address_list, {[context]})`

#### Example Template:

From `erpnext/public/js/templates/address_list.js`

New Address

{% for(var i=0, l=addr\_list.length; i
{%= \_\_("Edit") %}

#### {%= addr\_list[i].address\_type %}

{% if(addr\_list[i].is\_primary\_address) { %}
{%= \_\_("Primary") %}{% } %}
{% if(addr\_list[i].is\_shipping\_address) { %}
{%= \_\_("Shipping") %}{% } %}

{%= addr\_list[i].display %}

{% } %}
{% if(!addr\_list.length) { %}
{%= \_\_("No address added yet.") %}

{% } %}
