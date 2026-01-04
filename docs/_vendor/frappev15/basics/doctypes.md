1. [Introduction](doctypes.md#doctype)
2. [Modules](doctypes/modules.md)
3. [DocField](doctypes/docfield.md)
4. [Naming](doctypes/naming.md)
5. [Controllers](doctypes/controllers.md)
6. [Child DocType](doctypes/child-doctype.md)
7. [Single DocType](doctypes/single-doctype.md)
8. [Virtual DocType](doctypes/virtual-doctype.md)
9. [Actions and Links](doctypes/actions-and-links.md)
10. [Customizing DocTypes](doctypes/customize.md)

## Introduction

A DocType is the core building block of any application based on the Frappe Framework.
It describes the **Model** and the **View** of your data.
It contains what fields are stored for your data, and how they behave with respect to each other.
It contains information about how your data is named.
It also enables rich **Object Relational Mapper (ORM)** pattern which we will discuss later in this guide.
When you create a DocType, a JSON object is created which in turn creates a database table.

> ORM is just an easy way to read, write and update data in a database without writing explicit SQL statements.

#### Conventions

To enable rapid application development, Frappe Framework follows some standard conventions.

1. DocType is always singular. If you want to store a list of articles in the
   database, you should name the doctype **Article**.
2. Table names are prefixed with `tab`. So the table name for **Article** doctype
   is `tabArticle`.

The standard way to create a DocType is by typing *new doctype* in the search bar in the **Desk**.

*ToDo DocType*

A DocType not only stores fields, but also other information about how your data
behaves in the system. We call this **Meta**. Since this meta-data is also stored
in a database table, it makes it easy to change meta-data on the fly without writing
much code. Learn more about [Meta](doctypes.md#meta).

> A DocType is also a DocType. This means that we store meta-data as the part of the data.

After creating a DocType, Frappe can provide many features out-of-the-box.
If you go to `/app/todo` you will be routed to the List View in the desk.

*ToDo List*

Similarly, you get a Form View at the route `/app/todo/000001`.
The Form is used to create new docs and view them.

*ToDo Form*
