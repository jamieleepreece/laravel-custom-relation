# Laravel Custom Relationships

A custom relation for when stock relations aren't enough.

## Use this if...

* None of the stock Relations fit the bill. (BelongsToManyThrough, etc)
* You want your application to execute more optimised queries/relationships instead of performing multiple chained relationships
* You want more control over what tables you can join whilst using native Laravel methods e.g. `with` (eager loading) or `whereHas` (existence) queries
* You want control over every step of the relationship lifecycle

## Relationship Problems

Let's say we have 3 models:

- `User`
- `Role`
- `Permission`

Let's also say `User` has a many-to-many relation with `Role`, and `Role` has a many-to-many relation with `Permission`. 

So their models might look something like this. (I kept them brief on purpose.)

```php
class User
{
    public function roles() {
        return $this->belongsToMany(Role::class);
    }
}
```
```php
class Role
{
    public function users() {
        return $this->belongsToMany(User::class);
    }

    public function permissions() {
        return $this->belongsToMany(Permission::class);
    }
}
```
```php
class Permission
{
    public function roles() {
        return $this->belongsToMany(Role::class);
    }
}
```

**What if you wanted to get all the `Permission`s for a `User`, or all the `User`s with a particular `Permission`?** There no stock Relation in Laravel to descibe this. What we need is a `BelongsToManyThrough` but no such thing exists in stock Laravel.

#### Example Solution

```php
class User
{
    use HasCustomRelations;

    /**
     * Get the related permissions
     *
     * @return App\Relations\Custom
     */
    public function permissions()
    {
        return $this->custom(

            # The target Model you want to obtain in the relationship
            Permission::class,

            # Add base constraints (the base relationship query)
            function ($relation) 
            {
                $relation->getQuery()
                    // join the pivot table for permission and roles
                    ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
                    // join the pivot table for users and roles
                    ->join('role_user', 'role_user.role_id', '=', 'permission_role.role_id');
                    // for this user

                    # Specify model ID if if calling on single Model
                    if ($this->id)
                    {
                        $relation->getQuery()->where('role_user.user_id', $this->id);
                    }
            },

            # Add eager constraints
            function ($relation, $models) 
            {
                # Specify where IDs for multiple models
                $relation->getQuery()->whereIn('gam_units.site_id', collect($models)->pluck('id'));
            },
            # Map relationship models back into the parent models.
            # This example uses a dictionary for optimised sorting
            function($models, $results, $relation, $relationshipBuilder)
            {
                $dictionary = $relationshipBuilder->buildDictionary($results, 'user_id');

                foreach ($models as $model) {

                    if (isset($dictionary[$key = $model->getAttribute('id')]))
                    {
                        $values = $dictionary[$key];

                        $model->setRelation(
                            $relation, $relationshipBuilder->getRelated()->newCollection($values)
                        );
                    }
                }

                # Must return models
                return $models;
            },
            # Provide columns for existence join
            # For `has` (existence) queries, provide the correct columns for the join
            function($query, $parentQuery)
            {
                return $query->whereColumn(
                    $parentQuery->getModel()->getTable() . '.id', '=', 'role_user.user_id'
                );
            }
        );
    }
}
```

### Optimisation

Laravel provides an easy way to access relationships (data). However, distant relationships can become cumbersome to an application and provide both unnecessary overhead and N+1 issues.

Lets say for example you needed to chain four different relationships to obtain the data required, you would call `User::with('appointments.attended.doctor.notes`). This would perform five queries:
1. Firstly collect all users
2. Fetch all appointments with all user IDs
3. Then fetch all attended with all appointment IDs
4. Fetch the doctor via the appointment foreign key for doctor
5. Lastly fetch the notes associated with the appointment via foreign key

In some cases the DB can be redesigned to avoid this. However, if this is not possible you may become stuck with a less performant way to obtain the required data. The DB facade also provides a way to manually join all the above tables and obtain the data in a separate query. The caveat for this is that you have to write additional code to supplement your existing queries outside of other eager loading relationships.

To rectify this, custom relationships allow a complex join to be placed within a relationship wrapper. The above relationships can be aggregated together in one big table join, allowing the `Note`  models to be loaded directly onto the `User` models. This essentially reduces the five queries into two:

1. `User` models are collected
2. All `Note` models are queries and mapped into the parent user models






## Solution

The current implementation provides a lot of freedom for altering parts of the relationship lifecycle. Currently this is mainly closure based to provide more freedom, but can look a little messy.

```php
use LaravelCustomRelation\HasCustomRelations;

class Site
{
    use HasCustomRelations;

    /**
     * Get all distinct products which have been tagged
     *
     * @return App\Relations\Custom
     */
    public function products()
    {

        return $this->customRelationship(
            # The target Model you want to obtain in the relationship
            AdProduct::class,

            # Add base constraints (the base relationship query)
            function ($relation)
            {
                $relation->getQuery()
                    ->distinct()
                    ->join('ad_products_sizes', 'ad_products.id', '=', 'ad_products_sizes.product_id')
                    ->join('pivot_unit_size', 'ad_products_sizes.pivot_unit_size_id', '=', 'pivot_unit_size.id')
                    ->join('gam_units', 'pivot_unit_size.unit_id', '=', 'gam_units.id');

                    # Specify model ID if if calling on single Model
                    # If lazy loading from a single model, then provide the WHERE constraint
                    if ($this->id)
                    {
                        $relation->getQuery()->where('gam_units.site_id', $this->id);
                    }
            },
            # Add eager loading constraints
            # Specify all parent IDs for eager loading query
            function ($relation, $models)
            {
                # Specify where IDs for multiple models
                $relation->getQuery()->whereIn('gam_units.site_id', collect($models)->pluck('id'));
            },
            # Map relationship models back into the parent models.
            # This example uses a dictionary for optimised sorting
            function($models, $results, $relation, $relationshipBuilder)
            {
                $dictionary = $relationshipBuilder->buildDictionary($results, 'site_id');

                foreach ($models as $model) {

                    if (isset($dictionary[$key = $model->getAttribute('id')]))
                    {
                        $values = $dictionary[$key];

                        $model->setRelation(
                            $relation, $relationshipBuilder->getRelated()->newCollection($values)
                        );
                    }
                }

                # Must return models
                return $models;
            },
            # Provide columns for existence join
            # For `has` (existence) queries, provide the correct columns for the join
            function($query, $parentQuery)
            {
                return $query->whereColumn(
                    $parentQuery->getModel()->getTable() . '.id', '=', 'gam_units.site_id'
                );
            }
        );
    }
}
```

You could now do all the normal stuff for relations without having to query in-between relations first.

## TODO

- Refactor closure based arguments to provide methods
- Isolated class for clean relationship imports