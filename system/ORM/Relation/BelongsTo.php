<?php
/**
 * BelongsTo
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 * @date January 13th, 2016
 */

namespace Nova\ORM\Relation;

use Nova\ORM\Model;
use Nova\ORM\Relation;


class BelongsTo extends Relation
{
    protected $foreignKey;


    public function __construct($className, Model $model, $foreignKey = null)
    {
        parent::__construct($className, $model);

        // Process the foreignKey.
        if($foreignKey === null) {
            $this->foreignKey = $this->related->getForeignKey();
        } else {
            $this->foreignKey = $foreignKey;
        }
    }

    public function type()
    {
        return 'belongsTo';
    }

    public function get()
    {
        $id = $this->parent->getAttribute($this->foreignKey);

        return $this->query->findBy($this->related->getKeyName(), $id);
    }

}
