<?php

namespace App\Relations;

use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Custom extends Relation
{
    /**
     * The baseConstraints callback
     *
     * Define the base query for the relationship
     *
     * @var \Closure
     */
    protected $baseConstraints;

    /**
     * The eagerConstraints callback
     *
     * Define additional WHERE for eager loading e.g. a
     * WHERE IN for all the parent Models
     *
     * @var \Closure
     */
    protected $eagerConstraints;


    /**
    * The eagerMatcher model matcher.
    *
    * Callback to map obtained models to the parent
    *
    * @var \Closure
    */
    protected $eagerMatcher;

    /**
    * The existenceJoin callback
    *
    * Specify the columns in which to join the existence query
    *
    * @var \Closure
    */
    protected $existenceJoin;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Closure  $baseConstraints
     * @param  \Closure  $eagerConstraints
     * @param  \Closure  $eagerMatcher
     * @return void
     */
    public function __construct(Builder $query, Model $parent, Closure $baseConstraints, Closure $eagerConstraints, Closure $eagerMatcher, Closure $existenceJoin = null)
    {
        $this->baseConstraints = $baseConstraints;
        $this->eagerConstraints = $eagerConstraints;
        $this->eagerMatcher = $eagerMatcher;
        $this->existenceJoin = $existenceJoin;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        call_user_func($this->baseConstraints, $this);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        call_user_func($this->eagerConstraints, $this, $models);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return ($this->eagerMatcher)($models, $results, $relation, $this);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $columns = $this->query->getQuery()->columns ? [] : $columns;

        if ($columns == ['*']) {
            $columns = [$this->related->getTable().'.*'];
        }

        $builder = $this->query->applyScopes();

        $models = $builder->addSelect($columns)->getModels();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Provide relationship query to exist clause
     *
     * @todo   May need to expose this method for bespoke queries
     * @param  Builder $query
     * @param  Builder $parentQuery
     * @param  array   $columns
     * @return Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query = $this->query->select($columns);

        if ($this->existenceJoin)
        {
            return ($this->existenceJoin)($query, $parentQuery);
        }

        # Default join if none specified. Join the target table with a column name constructed from the parent tables name and id.
        return $this->query->select($columns)->whereColumn(
            $this->parent->getTable() . '.id', '=', $this->query->getModel()->getTable() . '.' . $this->parent->getTable() . '_id'
        );
    }
}
