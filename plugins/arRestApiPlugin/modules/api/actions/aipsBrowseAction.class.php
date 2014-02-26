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

class APIAIPsBrowseAction extends QubitAPIAction
{
  protected function get($request)
  {
    ProjectConfiguration::getActive()->loadHelpers('Qubit');

    $this->query = new \Elastica\Query();
    $this->queryBool = new \Elastica\Query\Bool();
    $this->filterBool = new \Elastica\Filter\Bool;

    // Limit
    if (isset($request->limit) && ctype_digit($request->limit))
    {
      $this->query->setLimit($request->limit);
    }

    // Skip
    if (isset($request->skip) && ctype_digit($request->skip))
    {
      $this->query->setFrom($request->skip);
    }

    // Sort and direction, default: filename, asc
    if (!isset($request->sort))
    {
      $request->sort = 'filename';
    }

    if (!isset($request->sort_direction))
    {
      $request->sort_direction = 'asc';
    }

    $this->query->setSort(array($request->sort => $request->sort_direction));

    // Query
    $this->queryBool->addMust(new \Elastica\Query\MatchAll());
    $this->query->setQuery($this->queryBool);

    // Facets
    $facet = new \Elastica\Facet\Terms('class');
    $facet->setField('class.id');
    $facet->setSize(10);

    $this->query->addFacet($facet);

    // TODO: Add filters
    if (0 < count($this->filterBool->toArray()))
    {
      $this->query->setFilter($this->filterBool);
    }

    $resultSet = QubitSearch::getInstance()->index->getType('QubitAip')->search($this->query);

    // Build array from results
    $resultsES = array();
    foreach ($resultSet as $hit)
    {
      $doc = $hit->getData();

      $aip = array();
      $aip['id'] = $hit->getId();
      $aip['name'] = $doc['filename'];
      $aip['uuid'] = $doc['uuid'];
      $aip['size'] = $doc['sizeOnDisk'];
      $aip['created_at'] = $doc['createdAt'];
      $aip['class'] = get_search_i18n($doc['class'][0], 'name');
      $aip['part_of']['id'] = $doc['partOf'][0]['id'];
      $aip['part_of']['title'] = get_search_i18n($doc['partOf'][0], 'title');

      // Parent is no longer needed

      $resultsES['aips']['results'][] = $aip;
    }

    $facets = array();
    foreach ($resultSet->getFacets() as $name => $facet)
    {
      // Pass if the facet is empty
      if (!isset($facet['terms']) && !isset($facet['count']))
      {
        continue;
      }

      foreach ($facet['terms'] as $term)
      {
        $facetTerm = '';
        switch ($name)
        {
          case 'class':
            if (null !== $item = QubitTerm::getById($term['term']))
            {
              $facetTerm = $item->getName(array('cultureFallback' => true));
            }

            break;

          default:
            $facetTerm = $term['term'];
        }

        $facets[$name]['terms'][] = array(
          'term' => $facetTerm,
          'count' => $term['count']);
      }
    }

    $resultsES['aips']['facets'] = $facets;

    // Overview
    $overview = array();

    $this->query = new \Elastica\Query();
    $this->queryBool = new \Elastica\Query\Bool();
    $this->queryBool->addMust(new \Elastica\Query\MatchAll());

    $facet = new \Elastica\Facet\TermsStats('total_sizes');
    $facet->setKeyField('class.id');
    $facet->setValueField('sizeOnDisk');

    $this->query->setQuery($this->queryBool);
    $this->query->addFacet($facet);

    $resultSet = QubitSearch::getInstance()->index->getType('QubitAip')->search($this->query);

    $totalSize = $totalCount = 0;
    foreach ($resultSet->getFacets() as $name => $facet)
    {
      // Pass if the facet is empty
      if (!isset($facet['terms']))
      {
        continue;
      }

      foreach ($facet['terms'] as $term)
      {
        $facetTerm = '';
        switch ($name)
        {
          case 'total_sizes':
            if (null !== $item = QubitTerm::getById($term['term']))
            {
              $facetTerm = $item->getName(array('cultureFallback' => true));
            }

            break;

          default:
            $facetTerm = $term['term'];
        }

        $overview[$facetTerm] = array(
          'size' => $term['total'],
          'count' => $term['count']);

        $totalSize += $term['total'];
        $totalCount += $term['count'];
      }

      // TODO? Update Elastica to use aggregations for the totals
      $overview['total'] = array(
        'size' => $totalSize,
        'count' => $totalCount);
    }

    $resultsES['overview'] = $overview;

    // Test data
    $results = array(

      /*
       * Overview
       */
      'overview' => array(
        'total' => array(
          'size' => 1062650070958,
          'count' => 16),
        'artwork' => array(
          'size' => 332430468710,
          'count' => 8),
        'software' => array(
          'size' => 214823526727,
          'count' => 4),
        'documentation' => array(
          'size' => 85899345920,
          'count' => 4),
        'unclassified' => array(
          'size' => 418115066265,
          'count' => 2)),

      /*
       * AIPs
       */
      'aips' => array(
        'results' => array(
          array(
            'id' => 1,
            'name' => 'SymCity_Box_scan_1-1-F9506513-0A19-41B4-B44B-D1A9F86ABEEA',
            'uuid' => 'f9506513-0a19-41b4-b44b-d1a9f86abeea',
            'size' => 15762529976,
            'created_at' => '2013-08-21 11:45:06 EST',
            'class' => 'Unclassified',
            'parent' => array(
              'id' => 1,
              'title' => 'SimCity 2000'),
            'part_of' => array(
              'id' => 1,
              'title' => 'SimCity 2000')),
          array(
            'id' => 2,
            'name' => 'SymCity_Box_scan_2-1-F9506513-0A19-41B4-B44B-D1A9F86ABEEA',
            'uuid' => 'f9506513-0a19-41b4-b44b-d1a9f86abeea',
            'size' => 25762529976,
            'created_at' => '2013-08-21 11:45:06 EST',
            'class' => 'Unclassified',
            'parent' => array(
              'id' => 1,
              'title' => 'SimCity 2000'),
            'part_of' => array(
              'id' => 1,
              'title' => 'SimCity 2000')),
          array(
            'id' => 3,
            'name' => 'SymCity_Box_scan_3-1-F9506513-0A19-41B4-B44B-D1A9F86ABEEA',
            'uuid' => 'f9506513-0a19-41b4-b44b-d1a9f86abeea',
            'size' => 35762529976,
            'created_at' => '2013-08-21 11:45:06 EST',
            'class' => 'Unclassified',
            'parent' => array(
              'id' => 1,
              'title' => 'SimCity 2000'),
            'part_of' => array(
              'id' => 1,
              'title' => 'SimCity 2000')),
        ),

      /*
       * Facets
       */
      'facets' => array(
        'class' => array(
          'terms' => array(
            array(
              'term' => 'Artwork',
              'count' => 10),
            array(
              'term' => 'Software',
              'count' => 4),
            array(
              'term' => 'Documentation',
              'count' => 3),
            array(
              'term' => 'Unclassified',
              'count' => 3))),
        'object_type' => array(
          'terms' => array(
            array(
              'term' => 'Image',
              'count' => 10),
            array(
              'term' => 'Audio',
              'count' => 3),
            array(
              'term' => 'Video',
              'count' => 2),
            array(
              'term' => 'Text',
              'count' => 4),
            array(
              'term' => 'Other',
              'count' => 32))))),

      /*
       * TMS Work MetaData
       */
      'tms_metadata' => array(
        'accession_id' => '1098.2005.a-c',
        'object_id' => '100620',
        'title' => 'Play Dead; Real time',
        'date' => '2003',
        'artist' => 'Douglas Gordon',
        'medium' => 'Three-channel video',
        'dimensions' => '19:11 min, 14:44 min. (on larger screens), 21:58 min. (on monitor). Minimum Room Size: 24.8m x 13.07m',
        'description' => 'Exhibition materials: 3 DVD and players, 2 projectors, 3 monitor, 2 screens. The complete work is a three-screen piece, consisting of one retro projection, one front projection and one monitor. See file for installation instructions. One monitor and two projections on screens 19.69 X 11.38 feet. Viewer must be able to walk around screens.'),

      /*
       * Digital Objects
       */
      'digital_objects' => array(
        'storage_total' => '10776432223432',
        'related_total' => array(
          'digital_objects' => 1,
          'aips' => 12),
        'objects' => array(
          'artwork' => array(
            'total' => 1,
            'total_size' => '262453654232'),
          'documentation' => array(
            'total' => 0,
            'total_size' => '0'),
          'unclassified' => array(
            'total' => 0,
            'total_size' => '0')))
      );

    return $resultsES;
  }
}