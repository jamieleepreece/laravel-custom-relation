<?php

namespace App\Relations\Traits;

use App\Relations\Custom;
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
    * @param  Closure  $baseConstraints
    * @param  String   $localKey
    * @param  String   $foreignKey
    * @param  Closure  $eagerConstraints
    * @param  Closure  $eagerMatcher
    * @param  Closure  $existenceJoin
    *
    * @return Custom
    */
    public function customRelationship
    (
        String $related,
        Closure $baseConstraints,
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
            $eagerConstraints,
            $eagerMatcher,
            $existenceJoin,
            $localKey,
            $foreignKey,
        );
    }
}
