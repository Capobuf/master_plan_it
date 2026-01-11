Create a frappe app scaffold using the bench CLI.

## Create app

Before we start, make sure you're in a bench directory. To confirm, run `bench find .`:

```
$ bench find .
/home/frappe/frappe-bench is a bench directory!
```

To create our Library Management app, run the `new-app` command:

```
$ bench new-app library_management
App Title (default: Library Management):
App Description: Library Management System
App Publisher: Faris Ansari
App Email: faris@example.com
App Icon (default 'octicon octicon-file-directory'):
App Color (default 'grey'):
App License (default 'MIT'):
'library_management' created at /home/frappe/frappe-bench/apps/library_management

Installing library_management
$ ./env/bin/pip install -q -U -e ./apps/library_management
$ bench build --app library_management
yarn run v1.22.4
$ FRAPPE_ENV=production node rollup/build.js --app library_management
Production mode
✔ Built js/moment-bundle.min.js
✔ Built js/libs.min.js
✨  Done in 1.95s.
```

You will be prompted with details of your app, fill them up and an app named `library_management` will be created in the `apps` folder.

To see a complete list of all icons supported in the octicons library, check out <https://primer.style/octicons/>

## App directory structure

Your app directory structure should look something like this:

```
apps/library_management
├── README.md
├── library_management
│   ├── hooks.py
│   ├── library_management
│ │ └── __init__.py
│ ├── modules.txt
│ ├── patches.txt
│ ├── public
│ │ ├── css
│ │ └── js
│ ├── templates
│ │ ├── __init__.py
│ │ ├── includes
│ │ └── pages
│ │ └── __init__.py
│ └── www
└── pyproject.toml
```

Next: Create a Site
