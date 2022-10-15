# Laravel Custom Relationships

A custom relation wrapper for when stock relations aren't enough.

## Do I need custom relationships? You may if

* None of the stock Relations fit the bill. (BelongsToManyThrough, etc)
* You want/need your application to execute more optimised queries/relationships instead of performing multiple chained relationships (reduce overhead & N+1)
* You want more control over what tables you can join whilst using native Laravel methods e.g. `with` (eager loading) or `whereHas` (existence) queries
* You want control over every step of the relationship lifecycle

## Use Cases

### Basic Overview

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

#### Example

Custom relationship for fetching users permissions

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
        return $this->customRelationship(
            related: Permission::class,
            baseConstraints: function ($relation)
            {
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
                }
            },
            foreignKey: 'role_user.user_id'
        );
    }
}
```
The first two named arguments are required to define a custom relationship. The `related` argument is the NS for the target `Model` and the `baseConstraints` is for providing the base query of the custom relationship.

The `foreignKey` here is optional, but is passed so that default logic in the relationship lifecycle can be applied, such as mapping models to the parent, existence queries and eager loading. However, if you wanted to write your own handlers then you can pass through additional closures like so

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
                $relation->getQuery()->whereIn('role_user.user_id', collect($models)->pluck('id'));
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
            },
        );
    }
}
```

### For Optimising Queries 

Laravel provides an easy way to access relationships (data). However, distant relationships can become cumbersome to an application and provide both unnecessary overhead and in some cases N+1 issues.

Consider a scenario where an existing DB cannot be easily altered and the data needed has to be eager loaded via multiple chained relationships. Lets say you wanted to get all discounted products for all users over the past year. The eager loading approach for this would look like this:

```php
User::with('orders.lines.product.discounts');
```

This would perform the following queries:

1. Fetch all users
2. Fetch all orders for all users (`WHERE orders.user_id user IN (...[IDs])`)
3. Fetch all line items for every order (`WHERE line_items.order_id user IN (...[IDs])`)
4. Fetch all products for every line (`WHERE products.id user IN (...[IDs])`)
4. Fetch all discounts via pivot for every product (`WHERE discounts.id user IN (...[IDs])`)

In some cases the DB can be redesigned to avoid this. However, if this is not possible you may become stuck with a less performant way to obtain the required data. The DB facade also provides a way to manually join all the above tables and obtain the data in a separate query. The caveat for this is that you have to write additional code to supplement your existing queries outside of other eager loading relationships.

To rectify this, custom relationships allow a complex join to be placed within a relationship wrapper. The above relationships can be aggregated together in one big table join, allowing the `Discount`  models to be loaded directly onto the `User` models. This essentially reduces five queries and additional overhead into two:

1. `User` models are collected
2. All `Discount` models are collected and mapped into the parent models

#### Example

```php
class User
{
    use HasCustomRelations;

    /**
     * Get all distinct products which have been tagged
     *
     * @return App\Relations\Custom
     */
    public function discountedProducts()
    {
        return $this->customRelationship(

            # The target Model you want to obtain in the relationship
            related: Discount::class,

            # Add base constraints (the base relationship query)
            baseConstraints: function ($relation)
            {
                # Query for the discounts table
                $relation->getQuery()
                    ->distinct()
                    ->join('products_discount_pivot', 'discount.id', '=', 'products_discount_pivot.discount_id')
                    ->join('products', 'products_discount_pivot.product_id', '=', 'products.id')
                    ->join('line_items', 'products.id', '=', 'line_items.product_id')
                    ->join('orders', 'line_items.order_id', '=', 'orders.id')

                    # Associate the target orders with the user ID
                    if ($this->id)
                    {
                        $relation->getQuery()->where('orders.user_id', $this->id);
                    }
            },

            foreignKey: 'orders.user_id'
        );
    }
}
```

This query will now provide an optimised way to gather distant relationships without additional overhead in your application. This is just an example, but is intended to be scaled up to applications where optimisations on every level are required.

## Arguments

1. `related`: The fully qualified namespace of the target `Model` (what we want to fetch)
2. `baseConstraints`: The base relationship query 
3. `eagerConstraints`: The additional constraint to be applied when eager loading
4. `eagerMatcher`: The map function to map collected models into the parent
5. `existenceJoin`: The additional constraint to be applied when using `has` (EXISTS)
6. `localKey`: Custom local key
7. `foreignKey`: The foreign key. Needed if not specifying your own optional query closures

## Debugging Relationships
Recommend some sort of query debugging package, such as Clockwork, as well as the typical `dd()` within closures etc.