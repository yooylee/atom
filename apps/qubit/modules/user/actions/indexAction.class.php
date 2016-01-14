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

class UserIndexAction extends sfAction
{
  public function execute($request)
  {
    $this->resource = $this->getRoute()->resource;

    foreach(array('crudApiKey', 'oaiApiKey') as $propertyName)
    {
      // Get OAPI key value, if any
      $keyProperty = QubitProperty::getOneByObjectIdAndName($this->resource->id, $propertyName);

      if (null != $keyProperty)
      {
        $underscored = sfInflector::underscore($propertyName);
        $this->$underscored = $keyProperty->value;
      }
    }

    // Except for administrators, only allow users to see their own profile
    if (!$this->context->user->isAdministrator())
    {
      if ($this->resource->id != $this->context->user->getAttribute('user_id'))
      {
        $this->redirect('admin/secure');
      }
    }
  }
}
