---
applyTo: "app/Http/**,app/Policies/**,app/Queries/**,app/Transformers/**"
---
# HTTP / domain rules
- Canonical resource: `CustomerController` + `CustomerRequest` + `CustomerPolicy` + `Queries\Customers\CustomerListQuery` + `CustomerListTransformer`. Mirror it (never reinvent it) when adding a resource.
- Store/update: catch `QueryException` and call `UniqueConstraintViolation::rethrowAsValidationError()`. Restore flows return their own conflict response instead.
- Deleting or force-deleting a parent is blocked if children exist, including trashed ones (`withTrashed()->exists()`). Customer→Project and Project→Epic work the same way.
- Form Requests authorize through the resource policy. Policies currently allow every verified user; this is documented in ARCHITECTURE.md, do not add roles unasked.
- List querying lives in `app/Queries`, row shaping in `app/Transformers`; keep controllers thin.
