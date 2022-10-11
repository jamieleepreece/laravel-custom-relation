# Laravel Custom Relation

A custom relation for when stock relations aren't enough.

## Use this if...

* None of the stock Relations fit the bill. (BelongsToManyThrough, etc)

## Example (Outdated)

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