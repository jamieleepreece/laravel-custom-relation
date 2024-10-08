<?php

namespace LaravelCustomRelation;

use LaravelCustomRelation\Relations\Custom;
use Closure;

trait HasCustomRelations
{
    /**
    * Define a custom relationship.
    *
    * If eager loading many, the query must select the parent id so that
    * the query result can then be pushed into the models as a relation.
    *
    * @param  string   $related           Fully qualified namespace of the target Model
    * @param  Closure  $baseConstraints   Base relationship query
    * @param  Closure  $singleConstraints Optional constraints for loading relationship for a single model
    * @param  Closure  $eagerConstraints  Optional constraints for eager loading
    * @param  Closure  $eagerMatcher      Optional mapping function for fetched relationships
    * @param  Closure  $existenceJoin     Optional constraint for joining existence query
    * @param  string   $localKey          Optional local key. Default generated is parent model with it's primary key
    * @param  string   $foreignKey        Optional foreign key
    *
    * @return Custom
    */
    public function customRelationship
    (
        string $related,
        Closure $baseConstraints,
        Closure $singleConstraints = null,
        Closure $eagerConstraints = null,
        Closure $eagerMatcher = null,
        Closure $existenceJoin = null,
        string $localKey = null,
        string $foreignKey = null,
    ): Custom
    {
        $instance = new $related;
        $query = $instance->newQuery();

        return new Custom(
            $query,
            $this,
            $baseConstraints,
            $singleConstraints,
            $eagerConstraints,
            $eagerMatcher,
            $existenceJoin,
            $localKey,
            $foreignKey,
        );
    }
}
