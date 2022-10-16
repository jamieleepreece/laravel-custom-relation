<?php

namespace LaravelCustomRelations;

use LaravelCustomRelations\Relations\Custom;
use Closure;

trait HasCustomRelations
{
    /**
    * Define a custom relationship.
    *
    * If eager loading many, the query must select the parent id so that
    * the query result can then be pushed into the models as a relation.
    *
    * @param  String   $related           Fully qualified namespace of the target Model
    * @param  Closure  $baseConstraints   Base relationship query
    * @param  Closure  $singleConstraints Optional constraints for loading relationship for a single model
    * @param  Closure  $eagerConstraints  Optional constraints for eager loading
    * @param  Closure  $eagerMatcher      Optional mapping function for fetched relationships
    * @param  Closure  $existenceJoin     Optional constraint for joining existence query
    * @param  String   $localKey          Optional local key. Default generated is parent model with it's primary key
    * @param  String   $foreignKey        Optional foreign key
    *
    * @return Custom
    */
    public function customRelationship
    (
        String $related,
        Closure $baseConstraints,
        Closure $singleConstraints = null,
        Closure $eagerConstraints = null,
        Closure $eagerMatcher = null,
        Closure $existenceJoin = null,
        String $localKey = null,
        String $foreignKey = null,
    )
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
