<?php

namespace LaravelCustomRelation\Relations;

use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Custom extends Relation
{
    /**
     * The baseConstraints callback
     *
     * Define the base query for the relationship
     *
     * @var Closure
     */
    protected $baseConstraints;

    /**
     * The singleConstraints callback
     *
     * Define the query for loading the relationship
     * on a single Model
     *
     * @var Closure
     */
    protected $singleConstraints;

    /**
     * The eagerConstraints callback
     *
     * Define additional WHERE for eager loading e.g. a
     * WHERE IN for all the parent Models
     *
     * @var Closure
     */
    protected $eagerConstraints;

    /**
    * The eagerMatcher model matcher.
    *
    * Callback to map obtained models to the parent
    *
    * @var Closure
    */
    protected $eagerMatcher;

    /**
    * The existenceJoin callback
    *
    * Specify the columns in which to join the existence query
    *
    * @var Closure
    */
    protected $existenceJoin;

    /**
     * The local key on the relationship
     *
     * Only used if using default logic instead of closures
     *
     * @var string
     */
    protected $localKey;

    /**
     * The local key on the relationship
     *
     * Only used if using default logic instead of closures
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  Builder  $query
     * @param  Model    $parent
     * @param  Closure  $baseConstraints
     * @param  Closure  $singleConstraints
     * @param  Closure  $eagerConstraints
     * @param  Closure  $eagerMatcher
     * @param  Closure  $existenceJoin
     * @param  String   $localKey
     * @param  String   $foreignKey
     * @return void
     */
    public function __construct
    (
        Builder $query,
        Model $parent,
        Closure $baseConstraints,
        Closure $singleConstraints = null,
        Closure $eagerConstraints = null,
        Closure $eagerMatcher = null,
        Closure $existenceJoin = null,
        String $localKey = null,
        String $foreignKey = null,
    )
    {
        $this->baseConstraints = $baseConstraints;
        $this->singleConstraints = $singleConstraints;
        $this->eagerConstraints = $eagerConstraints;
        $this->eagerMatcher = $eagerMatcher;
        $this->existenceJoin = $existenceJoin;

        # Set the default local key as the parent's primary key
        $this->localKey = ($localKey) ? $localKey : $parent->getKeyName() ?? 'id';
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the where clause for a single model the relation query.
     *
     * @return void
     */
    protected function addSingleParentWhereConstraints()
    {
        if ($this->singleConstraints)
        {
            ($this->singleConstraints)($this);
            return;
        }

        if (static::$constraints)
        {
            $this->query->where(
                $this->foreignKey, '=', $this->parent->{$this->localKey}
            );
        }
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        call_user_func($this->baseConstraints, $this);

        $this->addSingleParentWhereConstraints();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if ($this->eagerConstraints === null)
        {
            # Select the foreign key, so that it can be picked up on `match()`
            $this->query->addSelect($this->foreignKey);
            # Also select all from target table
            $this->query->addSelect($this->query->getModel()->getTable() . '.*');

            $this->query->whereIn($this->foreignKey, collect($models)->pluck($this->localKey));
            return;
        }

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
     * @param  Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        if ($this->eagerMatcher)
        {
            return ($this->eagerMatcher)($models, $results, $relation, $this);
        }

        # Attempt to parse via specified keys
        if ($this->localKey && $this->foreignKey)
        {
            $getKey = function(String $subject)
            {
                return (Str::contains($subject, '.')) ? Str::after($subject, '.') : $subject;
            };

            $dictionary = $this->buildDictionary($results, $getKey($this->foreignKey));

            foreach ($models as $model) {

                if (isset($dictionary[$key = $model->getAttribute($getKey($this->localKey))]))
                {
                    $values = $dictionary[$key];

                    $model->setRelation(
                        $relation, $this->getRelated()->newCollection($values)
                    );
                }
            }

            # Must return models
            return $models;
        }
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
     * @return Collection
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
        # Provide base query again
        ($this->baseConstraints)($query);

        # Provide custom join
        if ($this->existenceJoin)
        {
            return ($this->existenceJoin)($query, $parentQuery);
        }

        # Attempt to use specified primary and foreign keys
        if ($this->localKey && $this->foreignKey)
        {
            # If table included, then dont prepend
            if (Str::contains($this->localKey, '.'))
            {
                $primary = $this->localKey;
            }
            else
            {
                $primary = $this->parent->getTable() . '.' . $this->localKey;
            }

            $secondary = $this->foreignKey;

            return $query->select($columns)->whereColumn(
                $primary, '=', $secondary
            );
        }

        # Default join if none specified. Join the target table with a column name constructed from the parent tables name and id.
        return $query->select($columns)->whereColumn(
            $this->parent->getTable() . '.id', '=', $this->query->getModel()->getTable() . '.' . $this->parent->getTable() . '_id'
        );
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  Collection  $results
     * @return array
     */
    public function buildDictionary(Collection $results, string $foreign_key)
    {
        $dictionary = [];

        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $result) {
            $dictionary[$result->{$foreign_key}][] = $result;
        }

        return $dictionary;
    }
}
