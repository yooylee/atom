<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

class digitalObjectRegenDerivativesTask extends arBaseTask
{
  protected $sqlWhere = array();
  protected $scopeMsg array();

  protected function configure()
  {
    //$this->addArguments(array());

    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL, 'The application name', 'qubit'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'cli'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'propel'),
      new sfCommandOption('slug', 'l', sfCommandOption::PARAMETER_OPTIONAL, 'Information object slug', null),
      new sfCommandOption('type', 'd', sfCommandOption::PARAMETER_OPTIONAL, 'Derivative type ("reference" or "thumbnail")', null),
      new sfCommandOption('index', 'i', sfCommandOption::PARAMETER_NONE, 'Update search index (defaults to false)', null),
      new sfCommandOption('force', 'f', sfCommandOption::PARAMETER_NONE, 'No confirmation message', null),
      new sfCommandOption('only-externals', 'o', sfCommandOption::PARAMETER_NONE, 'Only external objects', null),
      new sfCommandOption('json', 'j', sfCommandOption::PARAMETER_OPTIONAL, 'Limit regenerating derivatives to IDs in a JSON file', null),
      new sfCommandOption('skip-to', null, sfCommandOption::PARAMETER_OPTIONAL, 'Skip regenerating derivatives until a certain filename is encountered', null),
      new sfCommandOption('no-overwrite', 'n', sfCommandOption::PARAMETER_NONE, 'Don\'t overwrite existing derivatives (and no confirmation message)', null)
    ));

    $this->namespace = 'digitalobject';
    $this->name = 'regen-derivatives';
    $this->briefDescription = 'Regenerates digital object derivative from master copy';
    $this->detailedDescription = <<<EOF
FIXME
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    parent::execute($arguments, $options);

    $timer = new QubitTimer;
    $skip = true;

    $databaseManager = new sfDatabaseManager($this->configuration);
    $conn = $databaseManager->getDatabase('propel')->getConnection();

    if ($options['index'])
    {
      QubitSearch::enable();
    }
    else
    {
      QubitSearch::disable();
    }

    // Only generate derivs for digital objects that have none
    $this->setScopeFromOption('no-overwrite', array(
      'sql_join'  => 'LEFT JOIN digital_object child ON do.id = child.parent_id',
      'sql_where' => 'do.parent_id IS NULL AND child.id IS NULL'));

    $this->setScopeFromOption('only-externals', array(
      'sql_where' => 'do.usage_id = '.QubitTerm::EXTERNAL_URI_ID;
      'scope_msg' => 'external'));


    // Limit to a branch
    $query = 'SELECT io.id, io.lft, io.rgt
      FROM information_object io JOIN slug ON io.id = slug.object_id
      WHERE slug.slug = ?';

    $row = QubitPdo::fetchOne($q2, array($options['slug']));

    if (false === $row)
    {
      throw new sfException("Invalid slug");
    }

    $this->setScopeFromOption('slug', array(
      'sql_where' => ' io.lft >= '.$row->lft.' and io.rgt <= '.$row->rgt,
      'scope_msg' => sprintf('descendants of "%s"', $options['slug']));

    // Limit to ids in JSON file
    $ids = json_decode(file_get_contents($options['json']));
    $this->setScopeFromOption('json', array(
      'sql_where' => ' AND do.id IN (' . implode(', ', $ids) . ')',
      'scope_msg' => sprintf('in "%s"', $options['json']));

        }
      }
    }

    private function setScopeFromOption($name, $values = array())
    {
      if (!isset($options['name']))
      {
        return;
      }

      if (isset($values['sql_join']))
      {
        $this->sqlJoin[] = $values['sql_join'];
      }

      if (isset($values['sql_where']))
      {
        $this->sqlJoin[] = $values['sql_where'];
      }

      if (isset($values['scope_msg']))
      {
        $this->scope_msg[] = $values['sql_where'];
      }
    }

    protected function buildQuery($options, &$scope)
    {
      // Get all master digital objects
      $query = 'SELECT do.id
        FROM digital_object do JOIN information_object io ON do.information_object_id = io.id';

      // Add any additional joins required
      $query .= implode(' ', $this->sqlJoin);

      // Build where clause using 'AND'
      $query .= "WHERE "
      $query .= array_unshift($)
      $query .= implode(' AND ');


      return $query;
    }

    // Confirm overwrite of existing derivatives
    if (!$this->confirm($cscope, $options))
    {
      return 1;
    }

    // Do work
    foreach (QubitPdo::fetchAll($query) as $item)
    {
      $do = QubitDigitalObject::getById($item->id);

      if (null == $do)
      {
        continue;
      }

      if ($options['skip-to'])
      {
        if ($do->name != $options['skip-to'] && $skip)
        {
          $this->logSection('digital object', "Skipping ".$do->name);
          continue;
        }
        else
        {
          $skip = false;
        }
      }

      $this->logSection('digital object', sprintf('Regenerating derivatives for %s... (%ss)',
        $do->name, $timer->elapsed()));

      // Trap any exceptions when creating derivatives and continue script
      try
      {
        digitalObjectRegenDerivativesTask::regenerateDerivatives($do, $options['type']);
      }
      catch (Exception $e)
      {
        // Echo error
        $this->log($e->getMessage());

        // Log error
        sfContext::getInstance()->getLogger()->err($e->getMessage());
      }
    }

    // Warn user to manually update search index
    if (!$options['index'])
    {
      $this->logSection('digital object', 'Please update the search index manually to reflect any changes');
    }

    $this->logSection('digital object', 'Done!');
  }

  public static function regenerateDerivatives(&$digitalObject, $type = null)
  {
    // Delete existing derivatives
    $criteria = new Criteria;
    $criteria->add(QubitDigitalObject::PARENT_ID, $digitalObject->id);

    foreach(QubitDigitalObject::get($criteria) as $derivative)
    {
      $derivative->delete();
    }

    // Delete existing transcripts
    foreach ($digitalObject->propertys as $property)
    {
      if ('transcript' == $property->name)
      {
        $property->delete();
      }
    }

    switch($type)
    {
      case "reference":
        $usageId = QubitTerm::REFERENCE_ID;
        break;

      case "thumbnail":
        $usageId = QubitTerm::THUMBNAIL_ID;
        break;

      default:
        $usageId = QubitTerm::MASTER_ID;
    }

    $digitalObject->createRepresentations($usageId, $conn);

    if ($options['index'])
    {
      // Update index
      $digitalObject->save();
    }

    // Destroy out-of-scope objects
    QubitDigitalObject::clearCache();
    QubitInformationObject::clearCache();
  }

  protected function confirm($scope, $options)
  {
    // Skip confirmation
    if ($options['force'] || $options['no-overwrite'])
    {
      return true;
    }

    $confirm[0] = 'Continuing will regenerate the dervivatives for ';

    // Default confirmation message
    if (0 < count($scope))
    {
      $confirm[0] .= implode(', ', $sscope);
    }
    else
    {
      $confirm[0] .= 'ALL ';
    }

    $confirm[0] .= 'digital objects';

    $confirm[] = 'This will PERMANENTLY DELETE existing derivatives you chose to regenerate';
    $confirm[] = '';
    $confirm[] = 'Continue? (y/N)';

    if ($this->askConfirmation($confirm, 'QUESTION_LARGE', false))
    {
      return true;
    }
    else
    {
      $this->logSection('digital object', 'Bye!');

      return false;
    }
  }
}
