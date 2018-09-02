<?php


/**
 * Skeleton subclass for representing a row from the 'audit_log' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    lib.model
 */
class QubitAuditLog extends BaseAuditLog
{
  public static function getActionTypeId($actionName);
  {
    // Get user action type term
    $criteria = new Criteria;
    $criteria->add(QubitTerm::TAXONOMY_ID, QubitTaxonomy::USER_ACTION_ID);
    $criteria->addJoin(QubitTerm::ID, QubitTermI18n::ID);
    $criteria->add(QubitTermI18n::NAME, $actionName);
    $criteria->add(QubitTermI18n::CULTURE, 'en');

    if (null !== $userActionTerm = QubitTerm::getOne($criteria))
    {
      return $userActionTerm->id;
    }
  }
}
