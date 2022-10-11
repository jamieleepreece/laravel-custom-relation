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
    * @param  string    $related
    * @param  \Closure  $baseConstraints
    * @param  \Closure  $eagerConstraints
    * @param  \Closure  $eagerMatcher
    * @param  \Closure  $existenceJoin
    * @return \LaravelCustomRelation\Relations\Custom
    */
    public function customRelationship($related, Closure $baseConstraints, Closure $eagerConstraints, Closure $eagerMatcher, Closure $existenceJoin = null)
    {
        $instance = new $related;
        $query = $instance->newQuery();

        return new Custom($query, $this, $baseConstraints, $eagerConstraints, $eagerMatcher, $existenceJoin);
    }
}
