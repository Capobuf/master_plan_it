You can add redirects by adding the `website_redirects` hook.

### Examples

```
website_redirects = [
 # absolute location
 {"source": "/from", "target": "https://mysite/from"},

 # relative location
 {"source": "/from", "target": "/main"},

 # use regex
 {"source": "/from/(.*)", "target": "/main/\1"}
]
```
