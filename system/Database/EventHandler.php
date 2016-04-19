<?php
/**
 * Event Handler.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 * @date January 22th, 2016
 *
 * Based on Pixie Query Builder: https://github.com/usmanhalalit/pixie
 */

namespace Nova\Database;

use Nova\Database\Query\Builder;
use Nova\Database\Query\Raw;


class EventHandler
{
    /**
     * @var array
     */
    protected $events = array();

    /**
     * @var array
     */
    protected $firedEvents = array();

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param $event
     * @param $table
     *
     * @return callable|null
     */
    public function getEvent($event, $table = ':any')
    {
        if ($table instanceof Raw) {
            return null;
        }

        return isset($this->events[$table][$event]) ? $this->events[$table][$event] : null;
    }

    /**
     * @param          $event
     * @param string   $table
     * @param callable $action
     *
     * @return void
     */
    public function registerEvent($event, $table, \Closure $action)
    {
        $table = $table ?: ':any';

        $this->events[$table][$event] = $action;
    }

    /**
     * @param          $event
     * @param string   $table
     *
     * @return void
     */
    public function removeEvent($event, $table = ':any')
    {
        unset($this->events[$table][$event]);
    }

    /**
     * @param QueryBuilderHandler $queryBuilder
     * @param                     $event
     * @return mixed
     */
    public function fireEvents($queryBuilder, $event)
    {
        $statements = $queryBuilder->getStatements();
        $tables = isset($statements['tables']) ? $statements['tables'] : array();

        // Events added with :any will be fired in case of any table,
        // we are adding :any as a fake table at the beginning.
        array_unshift($tables, ':any');

        // Fire all events
        foreach ($tables as $table) {
            // Fire before events for :any table
            if ($action = $this->getEvent($event, $table)) {
                // Make an event id, with event type and table
                $eventId = $event . $table;

                // Fire event
                $handlerParams = func_get_args();

                unset($handlerParams[1]); // We do not need $event

                // Add to fired list
                $this->firedEvents[] = $eventId;

                $result = call_user_func_array($action, $handlerParams);

                if (! is_null($result)) {
                    return $result;
                };
            }
        }
    }
}