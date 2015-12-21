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

class ApiInformationObjectsBrowseAction extends QubitApiAction
{
  protected function get($request)
  {
    $data = array();

    #$results = $this->getResults();
$results = $this->getR();
    $data['results'] = $results['results'];
    $data['facets'] = $results['facets'];
    $data['total'] = $results['total'];

    return $data;
  }

  protected function getR()
  {
    $query = new \Elastica\Query;
    #$query->setLimit($limit);
    #if (!empty($page))
    #{
    #  $query->setFrom(($page - 1) * $limit);
    #}

    $q = new \Elastica\Query\Bool;

    $q1 = new \Elastica\Query\Term;
    $q1->setTerm('ancestors', 1);
    $q->addMust($q1);
    #$q2 = new \Elastica\Query\Terms;
    #$q2->setTerms('levelOfDescriptionId', self::getLevels());
    #$q->addMust($q2);

    $query->setQuery($q);

    $i18n = sprintf('i18n.%s.', sfContext::getInstance()->getUser()->getCulture());
    switch ($sort)
    {
      case 'identifierDown':
        $query->setSort(array('identifier' =>
          array('order' => 'desc', 'ignore_unmapped' => true)));

        break;

      case 'titleUp':
        $query->setSort(array($i18n.'title.untouched' =>
          array('order' => 'asc', 'ignore_unmapped' => true)));

        break;

      case 'titleDown':
        $query->setSort(array($i18n.'title.untouched' =>
          array('order' => 'desc', 'ignore_unmapped' => true)));

        break;

      case 'levelUp':
        $query->setSort(array('levelOfDescriptionId' =>
          array('order' => 'asc', 'ignore_unmapped' => true)));

        break;

      case 'levelDown':
        $query->setSort(array('levelOfDescriptionId' =>
          array('order' => 'desc', 'ignore_unmapped' => true)));

        break;

      case 'dateUp':
        $query->setSort(array(
          'dates.startDate' => array('order' => 'asc', 'ignore_unmapped' => true),
          'dates.endDate' => array('order' => 'asc', 'ignore_unmapped' => true)));

        break;

      case 'dateDown':
        $query->setSort(array(
          'dates.startDate' => array('order' => 'desc', 'ignore_unmapped' => true),
          'dates.endDate' => array('order' => 'desc', 'ignore_unmapped' => true)));

        break;

      // Avoid sorting when we are just counting records
      case null:
        break;

      case 'identifierUp':
      default:
        $query->setSort(array('identifier' =>
          array('order' => 'asc', 'ignore_unmapped' => true)));
    }


    // Filter drafts
    $filterBool = new \Elastica\Filter\Bool;
    QubitAclSearch::filterDrafts($filterBool);
    if (0 < count($filterBool->toArray()))
    {
      $query->setFilter($filterBool);
    }

    return QubitSearch::getInstance()->index->getType('QubitInformationObject')->search($query);
  }




  protected function getResults()
  {
    // Create query objects
    $query = new \Elastica\Query;
    $queryBool = new \Elastica\Query\Bool;
    $queryBool->addMust(new \Elastica\Query\MatchAll);

    // Pagination and sorting
    $this->prepareEsPagination($query);
    $this->prepareEsSorting($query, array(
      'createdAt' => 'createdAt'));

    // Filter level_id
    if (isset($this->request->level_id) && ctype_digit($this->request->level_id))
    {
      $queryBool->addMust(new \Elastica\Query\Term(array('levelOfDescriptionId' => $this->request->level_id)));
    }

    // Show only root elements
    if (isset($this->request->only_root) && true === filter_var($this->request->only_root, FILTER_VALIDATE_BOOLEAN))
    {
      $queryBool->addMust(new \Elastica\Query\Term(array('parentId' => QubitInformationObject::ROOT_ID)));
    }

    // Filter query
    if (isset($this->request->query) && 1 !== preg_match('/^[\s\t\r\n]*$/', $this->request->query))
    {
      $queryString = new \Elastica\Query\QueryString($this->request->query);
      $queryString->setDefaultOperator('OR');

      $queryBool->addMust($queryString);
    }

    // Limit fields
    $query->setFields(array(
      'slug',
      'identifier',
      'inheritReferenceCode',
      'levelOfDescriptionId',
      'publicationStatusId',
      'ancestors',
      'parentId',
      'hasDigitalObject',
      'createdAt',
      'updatedAt',
      'sourceCulture',
      'i18n'));

    // Assign query
    $query->setQuery($queryBool);

    $resultSet = QubitSearch::getInstance()->index->getType('QubitInformationObject')->search($query);

    // Build array from results
    $results = array();
    foreach ($resultSet as $hit)
    {
      $doc = $hit->getData();
      $results[$hit->getId()] = $hit->getFields();
      $results[$hit->getId()]['id'] = (int)$hit->getId();
      $results[$hit->getId()]['title'] = get_search_i18n($doc, 'title');
    }

    $facets = array();

    return
      array(
        'total' => $resultSet->getTotalHits(),
        'facets' => $facets,
        'results' => $results);
  }

  protected function post($request, $payload)
  {
    $io = new QubitInformationObject();
    $io->parentId = QubitInformationObject::ROOT_ID;

    // TODO: restrict to allowed fields
    foreach ($payload as $field => $value)
    {
      $field = lcfirst(sfInflector::camelize($field));
      $io->$field = $value;
    }

    $io->save();
  }
}
