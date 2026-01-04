> Added in Version 12.1

Action and Links (also called Connections) are two ways to provide the end user more interaction with the document. The image below shows what they are :

## Actions

A DocType may have some `DocType Action` that will result in a button creation on the DocType View. Supported actions are:

1. **Server Action**: This will trigger a whitelisted server action.
2. **Route**: This will redirect to a given route.

### Configuration of Action

### Configuration of Action in custom app

To call an Action in you own app, you will need a python function decorated with `frappe.whitelist` :

```
import frappe

@frappe.whitelist()
def execute_function(*args,**kwargs):
 """
 This fonction will be executed when the Execute Action Button will be clicked
 """
 print('Hello World')
 # The data is transmitted via keyword argument
 print(kwargs)
```

This code should go somewhere inside you app, typically in a file like `apps/my_app/my_app/api.py`

And then, configure the correspondant Action path :

## Connections (Linked Documents)

A standard navigation aid to the DocType view is the `Connections` section on the dashboard. This helps the viewer identify at a glance which document types are connected to this DocType and can quickly create new related documents.

These links also support adding internal links (links to DocType in child tables).

### Configuration Connections

### Customization of Actions and Links

DocType Actions and Links are extensible via [Customize Form](customize.html)
