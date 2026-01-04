A Virtual DocField is a dynamic attribute of a given Document (or Record). It's a calculated attribute that isn't stored in the site database. This could be used for representing values that may be functions of other static Document attributes.

*Person Form*

The age of a person is a function of their date of birth ie if you know a person's birth day, you can figure out their age. Age is also a continuous value; it may change every year, or month, or day, even hour depending on what type of granularity you wish to have. Another attribute is a Person's name. Most common implementations will have a First, Middle and a Last Name, and for the view it would be all of them together like "Jon Raphael Doe". Although saving the full name as a separate attribute may not make so much sense when the strings can be easily concatenated. These are a couple of instances where Virtual DocFields make more sense.

*Person DocType*

Here we've added three fields to Person; two for First and Last names which are stored in the site database, and one that utilizes this data to populate the third field "Full Name". In this instance, the options field takes input for the return value of the respective virtual field.

*Person DocType - DocField*

We discussed the possibility of fields that depend on attributes in the system so far. But this could easily extend to something that doesn't depend on your DocType data alone. You may also want to fetch the statuses of multiple external services in their place, or anything else that you can map here instead.

## How to use Virtual DocFields

The steps involved in making this work are:

### 1. Define a Virtual DocField

Defining a Virtual DocField is fairly straight forward. Just checking on the "Virtual" check box under the DocField's configuration does this. Virtual DocFields don't create a corresponding column in the DocType's table. This makes the field "Read Only" in the Form Views.

> Note: Avoid making existing DocFields virtual unless you know what you're doing

### 2. Define a source for the field

The first step only adds a sort of a placeholder for the values. Without adding some code that dictates what the field should show, there's no field itself. There's two ways of doing this:

* By extending the DocType controllers

Adding a Python property with the same name as the virtual field should do this. This is the most flexible way to do this; you could daisy chain an internal API request, or fetch the data from multiple data sources, sky is the limit.

```
class Person(Document):
 @property
 def age(self):
 return frappe.utils.now_datetime() - self.creation
```

* Using the `DocField.options`

This is a bit more restrictive given it allows you to write code directly from Desk. Utils allowed in Server Scripts and Document attributes can be accessed through this. Equivalent of the above property maybe as follows:

```
frappe.utils.now_datetime() - self.creation
```

The above mentioned `Person.full_name` example uses Python's f-string feature to achive this in a similar way.

> Note: This should be preferred for relatively smaller scripting. Lookout for in-compatible types errors while using this

## Impact on internals

If you're wellversed with the physics of the Frappe world, this feature will appear quite predictable.

### Backend API

The `DatabaseQuery` methods or `Database` APIs will not return virtual values since they don't live in the Site Database.

### REST API

The `/api/method/frappe.desk.form.load.getdoc` and `/api/resource` APIs utilize `Document.get_valid_dict` which will compute Virtual values too. These APIs are used to render the Desk Form views too.

### Database

There's no footprint of virtual fields in the respective DocType's table. However, you may find corresponding records to give some evidence of their existence in the Custom Field, DocField tables that store DocType's Meta.
