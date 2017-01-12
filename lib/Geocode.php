<?php
	class Geocode
	{
		protected $oDB;

		protected $aLangPrefOrder = array();

		protected $bIncludeAddressDetails = false;
		protected $bIncludeExtraTags = false;
		protected $bIncludeNameDetails = false;

		protected $bIncludePolygonAsPoints = false;
		protected $bIncludePolygonAsText = false;
		protected $bIncludePolygonAsGeoJSON = false;
		protected $bIncludePolygonAsKML = false;
		protected $bIncludePolygonAsSVG = false;
		protected $fPolygonSimplificationThreshold = 0.0;

		protected $aExcludePlaceIDs = array();
		protected $bDeDupe = true;
		protected $bReverseInPlan = false;

		protected $iLimit = 20;
		protected $iFinalLimit = 10;
		protected $iOffset = 0;
		protected $bFallback = false;

		protected $aCountryCodes = false;
		protected $aNearPoint = false;

		protected $bBoundedSearch = false;
		protected $aViewBox = false;
		protected $sViewboxSmallSQL = false;
		protected $sViewboxLargeSQL = false;
		protected $aRoutePoints = false;

		protected $iMaxRank = 20;
		protected $iMinAddressRank = 0;
		protected $iMaxAddressRank = 30;
		protected $aAddressRankList = array();
		protected $exactMatchCache = array();

		protected $sAllowedTypesSQLList = false;

		protected $sQuery = false;
		protected $aStructuredQuery = false;

		function Geocode(&$oDB)
		{
			$this->oDB =& $oDB;
		}

		function setReverseInPlan($bReverse)
		{
			$this->bReverseInPlan = $bReverse;
		}

		function setLanguagePreference($aLangPref)
		{
			$this->aLangPrefOrder = $aLangPref;
		}

		function setIncludeAddressDetails($bAddressDetails = true)
		{
			$this->bIncludeAddressDetails = (bool)$bAddressDetails;
		}

		function getIncludeAddressDetails()
		{
			return $this->bIncludeAddressDetails;
		}

		function getIncludeExtraTags()
		{
			return $this->bIncludeExtraTags;
		}

		function getIncludeNameDetails()
		{
			return $this->bIncludeNameDetails;
		}

		function setIncludePolygonAsPoints($b = true)
		{
			$this->bIncludePolygonAsPoints = $b;
		}

		function getIncludePolygonAsPoints()
		{
			return $this->bIncludePolygonAsPoints;
		}

		function setIncludePolygonAsText($b = true)
		{
			$this->bIncludePolygonAsText = $b;
		}

		function getIncludePolygonAsText()
		{
			return $this->bIncludePolygonAsText;
		}

		function setIncludePolygonAsGeoJSON($b = true)
		{
			$this->bIncludePolygonAsGeoJSON = $b;
		}

		function setIncludePolygonAsKML($b = true)
		{
			$this->bIncludePolygonAsKML = $b;
		}

		function setIncludePolygonAsSVG($b = true)
		{
			$this->bIncludePolygonAsSVG = $b;
		}

		function setPolygonSimplificationThreshold($f)
		{
			$this->fPolygonSimplificationThreshold = $f;
		}

		function setDeDupe($bDeDupe = true)
		{
			$this->bDeDupe = (bool)$bDeDupe;
		}

		function setLimit($iLimit = 10)
		{
			if ($iLimit > 50) $iLimit = 50;
			if ($iLimit < 1) $iLimit = 1;

			$this->iFinalLimit = $iLimit;
			$this->iLimit = $this->iFinalLimit + min($this->iFinalLimit, 10);
		}

		function setOffset($iOffset = 0)
		{
			$this->iOffset = $iOffset;
		}

		function setFallback($bFallback = true)
		{
			$this->bFallback = (bool)$bFallback;
		}

		function setExcludedPlaceIDs($a)
		{
			// TODO: force to int
			$this->aExcludePlaceIDs = $a;
		}

		function getExcludedPlaceIDs()
		{
			return $this->aExcludePlaceIDs;
		}

		function setBounded($bBoundedSearch = true)
		{
			$this->bBoundedSearch = (bool)$bBoundedSearch;
		}

		function setViewBox($fLeft, $fBottom, $fRight, $fTop)
		{
			$this->aViewBox = array($fLeft, $fBottom, $fRight, $fTop);
		}

		function getViewBoxString()
		{
			if (!$this->aViewBox) return null;
			return $this->aViewBox[0].','.$this->aViewBox[3].','.$this->aViewBox[2].','.$this->aViewBox[1];
		}

		function setRoute($aRoutePoints)
		{
			$this->aRoutePoints = $aRoutePoints;
		}

		function setFeatureType($sFeatureType)
		{
			switch($sFeatureType)
			{
			case 'country':
				$this->setRankRange(4, 4);
				break;
			case 'state':
				$this->setRankRange(8, 8);
				break;
			case 'city':
				$this->setRankRange(14, 16);
				break;
			case 'settlement':
				$this->setRankRange(8, 20);
				break;
			}
		}

		function setRankRange($iMin, $iMax)
		{
			$this->iMinAddressRank = (int)$iMin;
			$this->iMaxAddressRank = (int)$iMax;
		}

		function setNearPoint($aNearPoint, $fRadiusDeg = 0.1)
		{
			$this->aNearPoint = array((float)$aNearPoint[0], (float)$aNearPoint[1], (float)$fRadiusDeg);
		}

		function setCountryCodesList($aCountryCodes)
		{
			$this->aCountryCodes = $aCountryCodes;
		}

		function setQuery($sQueryString)
		{
			$this->sQuery = $sQueryString;
			$this->aStructuredQuery = false;
		}

		function getQueryString()
		{
			return $this->sQuery;
		}


		function loadParamArray($aParams)
		{
			if (isset($aParams['addressdetails'])) $this->bIncludeAddressDetails = (bool)$aParams['addressdetails'];
			if ((float) CONST_Postgresql_Version > 9.2)
			{
				if (isset($aParams['extratags'])) $this->bIncludeExtraTags = (bool)$aParams['extratags'];
				if (isset($aParams['namedetails'])) $this->bIncludeNameDetails = (bool)$aParams['namedetails'];
			}
			if (isset($aParams['bounded'])) $this->bBoundedSearch = (bool)$aParams['bounded'];
			if (isset($aParams['dedupe'])) $this->bDeDupe = (bool)$aParams['dedupe'];

			if (isset($aParams['limit'])) $this->setLimit((int)$aParams['limit']);
			if (isset($aParams['offset'])) $this->iOffset = (int)$aParams['offset'];

			if (isset($aParams['fallback'])) $this->bFallback = (bool)$aParams['fallback'];

			// List of excluded Place IDs - used for more acurate pageing
			if (isset($aParams['exclude_place_ids']) && $aParams['exclude_place_ids'])
			{
				foreach(explode(',',$aParams['exclude_place_ids']) as $iExcludedPlaceID)
				{
					$iExcludedPlaceID = (int)$iExcludedPlaceID;
					if ($iExcludedPlaceID)
						$aExcludePlaceIDs[$iExcludedPlaceID] = $iExcludedPlaceID;
				}

				if (isset($aExcludePlaceIDs))
					$this->aExcludePlaceIDs = $aExcludePlaceIDs;
			}

			// Only certain ranks of feature
			if (isset($aParams['featureType'])) $this->setFeatureType($aParams['featureType']);
			if (isset($aParams['featuretype'])) $this->setFeatureType($aParams['featuretype']);

			// Country code list
			if (isset($aParams['countrycodes']))
			{
				$aCountryCodes = array();
				foreach(explode(',',$aParams['countrycodes']) as $sCountryCode)
				{
					if (preg_match('/^[a-zA-Z][a-zA-Z]$/', $sCountryCode))
					{
						$aCountryCodes[] = strtolower($sCountryCode);
					}
				}
				$this->aCountryCodes = $aCountryCodes;
			}

			if (isset($aParams['viewboxlbrt']) && $aParams['viewboxlbrt'])
			{
				$aCoOrdinatesLBRT = explode(',',$aParams['viewboxlbrt']);
				$this->setViewBox($aCoOrdinatesLBRT[0], $aCoOrdinatesLBRT[1], $aCoOrdinatesLBRT[2], $aCoOrdinatesLBRT[3]);
			}
			else if (isset($aParams['viewbox']) && $aParams['viewbox'])
			{
				$aCoOrdinatesLTRB = explode(',',$aParams['viewbox']);
				$this->setViewBox($aCoOrdinatesLTRB[0], $aCoOrdinatesLTRB[3], $aCoOrdinatesLTRB[2], $aCoOrdinatesLTRB[1]);
			}

			if (isset($aParams['route']) && $aParams['route'] && isset($aParams['routewidth']) && $aParams['routewidth'])
			{
				$aPoints = explode(',',$aParams['route']);
				if (sizeof($aPoints) % 2 != 0)
				{
					userError("Uneven number of points");
					exit;
				}
				$fPrevCoord = false;
				$aRoute = array();
				foreach($aPoints as $i => $fPoint)
				{
					if ($i%2)
					{
						$aRoute[] = array((float)$fPoint, $fPrevCoord);
					}
					else
					{
						$fPrevCoord = (float)$fPoint;
					}
				}
				$this->aRoutePoints = $aRoute;
			}
		}

		function setQueryFromParams($aParams)
		{
			// Search query
			$sQuery = (isset($aParams['q'])?trim($aParams['q']):'');
			if (!$sQuery)
			{
				$this->setStructuredQuery(@$aParams['amenity'], @$aParams['street'], @$aParams['city'], @$aParams['county'], @$aParams['state'], @$aParams['country'], @$aParams['postalcode']);
				$this->setReverseInPlan(false);
			}
			else
			{
				$this->setQuery($sQuery);
			}
		}

		function loadStructuredAddressElement($sValue, $sKey, $iNewMinAddressRank, $iNewMaxAddressRank, $aItemListValues)
		{
			$sValue = trim($sValue);
			if (!$sValue) return false;
			$this->aStructuredQuery[$sKey] = $sValue;
			if ($this->iMinAddressRank == 0 && $this->iMaxAddressRank == 30)
			{
				$this->iMinAddressRank = $iNewMinAddressRank;
				$this->iMaxAddressRank = $iNewMaxAddressRank;
			}
			if ($aItemListValues) $this->aAddressRankList = array_merge($this->aAddressRankList, $aItemListValues);
			return true;
		}

		function setStructuredQuery($sAmentiy = false, $sStreet = false, $sCity = false, $sCounty = false, $sState = false, $sCountry = false, $sPostalCode = false)
		{
			$this->sQuery = false;

			// Reset
			$this->iMinAddressRank = 0;
			$this->iMaxAddressRank = 30;
			$this->aAddressRankList = array();

			$this->aStructuredQuery = array();
			$this->sAllowedTypesSQLList = '';

			$this->loadStructuredAddressElement($sAmentiy, 'amenity', 26, 30, false);
			$this->loadStructuredAddressElement($sStreet, 'street', 26, 30, false);
			$this->loadStructuredAddressElement($sCity, 'city', 14, 24, false);
			$this->loadStructuredAddressElement($sCounty, 'county', 9, 13, false);
			$this->loadStructuredAddressElement($sState, 'state', 8, 8, false);
			$this->loadStructuredAddressElement($sPostalCode, 'postalcode' , 5, 11, array(5, 11));
			$this->loadStructuredAddressElement($sCountry, 'country', 4, 4, false);

			if (sizeof($this->aStructuredQuery) > 0) 
			{
				$this->sQuery = join(', ', $this->aStructuredQuery);
				if ($this->iMaxAddressRank < 30)
				{
					$sAllowedTypesSQLList = '(\'place\',\'boundary\')';
				}
			}
		}

		function fallbackStructuredQuery()
		{
			if (!$this->aStructuredQuery) return false;

			$aParams = $this->aStructuredQuery;

			if (sizeof($aParams) == 1) return false;

			$aOrderToFallback = array('postalcode', 'street', 'city', 'county', 'state');

			foreach($aOrderToFallback as $sType)
			{
				if (isset($aParams[$sType]))
				{
					unset($aParams[$sType]);
					$this->setStructuredQuery(@$aParams['amenity'], @$aParams['street'], @$aParams['city'], @$aParams['county'], @$aParams['state'], @$aParams['country'], @$aParams['postalcode']);
					return true;
				}
			}

			return false;
		}

		function getDetails($aPlaceIDs)
		{
			if (sizeof($aPlaceIDs) == 0)  return array();

			$sLanguagePrefArraySQL = "ARRAY[".join(',',array_map("getDBQuoted",$this->aLangPrefOrder))."]";

			// Get the details for display (is this a redundant extra step?)
			$sPlaceIDs = join(',',$aPlaceIDs);

			$sImportanceSQL = '';
			if ($this->sViewboxSmallSQL) $sImportanceSQL .= " case when ST_Contains($this->sViewboxSmallSQL, ST_Collect(centroid)) THEN 1 ELSE 0.75 END * ";
			if ($this->sViewboxLargeSQL) $sImportanceSQL .= " case when ST_Contains($this->sViewboxLargeSQL, ST_Collect(centroid)) THEN 1 ELSE 0.75 END * ";

			$sSQL = "select osm_type,osm_id,class,type,admin_level,rank_search,rank_address,min(place_id) as place_id, min(parent_place_id) as parent_place_id, calculated_country_code as country_code,";
			$sSQL .= "get_address_by_language(place_id, $sLanguagePrefArraySQL) as langaddress,";
			$sSQL .= "get_name_by_language(name, $sLanguagePrefArraySQL) as placename,";
			$sSQL .= "get_name_by_language(name, ARRAY['ref']) as ref,";
			if ($this->bIncludeExtraTags) $sSQL .= "hstore_to_json(extratags)::text as extra,";
			if ($this->bIncludeNameDetails) $sSQL .= "hstore_to_json(name)::text as names,";
			$sSQL .= "avg(ST_X(centroid)) as lon,avg(ST_Y(centroid)) as lat, ";
			$sSQL .= $sImportanceSQL."coalesce(importance,0.75-(rank_search::float/40)) as importance, ";
			$sSQL .= "(select max(p.importance*(p.rank_address+2)) from place_addressline s, placex p where s.place_id = min(CASE WHEN placex.rank_search < 28 THEN placex.place_id ELSE placex.parent_place_id END) and p.place_id = s.address_place_id and s.isaddress and p.importance is not null) as addressimportance, ";
			$sSQL .= "(extratags->'place') as extra_place ";
			$sSQL .= "from placex where place_id in ($sPlaceIDs) ";
			$sSQL .= "and (placex.rank_address between $this->iMinAddressRank and $this->iMaxAddressRank ";
			if (14 >= $this->iMinAddressRank && 14 <= $this->iMaxAddressRank) $sSQL .= " OR (extratags->'place') = 'city'";
			if ($this->aAddressRankList) $sSQL .= " OR placex.rank_address in (".join(',',$this->aAddressRankList).")";
			$sSQL .= ") ";
			if ($this->sAllowedTypesSQLList) $sSQL .= "and placex.class in $this->sAllowedTypesSQLList ";
			$sSQL .= "and linked_place_id is null ";
			$sSQL .= "group by osm_type,osm_id,class,type,admin_level,rank_search,rank_address,calculated_country_code,importance";
			if (!$this->bDeDupe) $sSQL .= ",place_id";
			$sSQL .= ",langaddress ";
			$sSQL .= ",placename ";
			$sSQL .= ",ref ";
			if ($this->bIncludeExtraTags) $sSQL .= ",extratags";
			if ($this->bIncludeNameDetails) $sSQL .= ",name";
			$sSQL .= ",extratags->'place' ";

			if (30 >= $this->iMinAddressRank && 30 <= $this->iMaxAddressRank)
			{
				$sSQL .= " union ";
				$sSQL .= "select 'T' as osm_type,place_id as osm_id,'place' as class,'house' as type,null as admin_level,30 as rank_search,30 as rank_address,min(place_id) as place_id, min(parent_place_id) as parent_place_id,'us' as country_code,";
				$sSQL .= "get_address_by_language(place_id, $sLanguagePrefArraySQL) as langaddress,";
				$sSQL .= "null as placename,";
				$sSQL .= "null as ref,";
				if ($this->bIncludeExtraTags) $sSQL .= "null as extra,";
				if ($this->bIncludeNameDetails) $sSQL .= "null as names,";
				$sSQL .= "avg(ST_X(centroid)) as lon,avg(ST_Y(centroid)) as lat, ";
				$sSQL .= $sImportanceSQL."-1.15 as importance, ";
				$sSQL .= "(select max(p.importance*(p.rank_address+2)) from place_addressline s, placex p where s.place_id = min(location_property_tiger.parent_place_id) and p.place_id = s.address_place_id and s.isaddress and p.importance is not null) as addressimportance, ";
				$sSQL .= "null as extra_place ";
				$sSQL .= "from location_property_tiger where place_id in ($sPlaceIDs) ";
				$sSQL .= "and 30 between $this->iMinAddressRank and $this->iMaxAddressRank ";
				$sSQL .= "group by place_id";
				if (!$this->bDeDupe) $sSQL .= ",place_id ";
				$sSQL .= " union ";
				$sSQL .= "select 'L' as osm_type,place_id as osm_id,'place' as class,'house' as type,null as admin_level,30 as rank_search,30 as rank_address,min(place_id) as place_id, min(parent_place_id) as parent_place_id,'us' as country_code,";
				$sSQL .= "get_address_by_language(place_id, $sLanguagePrefArraySQL) as langaddress,";
				$sSQL .= "null as placename,";
				$sSQL .= "null as ref,";
				if ($this->bIncludeExtraTags) $sSQL .= "null as extra,";
				if ($this->bIncludeNameDetails) $sSQL .= "null as names,";
				$sSQL .= "avg(ST_X(centroid)) as lon,avg(ST_Y(centroid)) as lat, ";
				$sSQL .= $sImportanceSQL."-1.10 as importance, ";
				$sSQL .= "(select max(p.importance*(p.rank_address+2)) from place_addressline s, placex p where s.place_id = min(location_property_aux.parent_place_id) and p.place_id = s.address_place_id and s.isaddress and p.importance is not null) as addressimportance, ";
				$sSQL .= "null as extra_place ";
				$sSQL .= "from location_property_aux where place_id in ($sPlaceIDs) ";
				$sSQL .= "and 30 between $this->iMinAddressRank and $this->iMaxAddressRank ";
				$sSQL .= "group by place_id";
				if (!$this->bDeDupe) $sSQL .= ",place_id";
				$sSQL .= ",get_address_by_language(place_id, $sLanguagePrefArraySQL) ";
			}

			$sSQL .= " order by importance desc";
			if (CONST_Debug) { echo "<hr>"; var_dump($sSQL); }
			$aSearchResults = $this->oDB->getAll($sSQL);

			if (PEAR::IsError($aSearchResults))
			{
				failInternalError("Could not get details for place.", $sSQL, $aSearchResults);
			}

			return $aSearchResults;
		}

		function getGroupedSearches($aSearches, $aPhraseTypes, $aPhrases, $aValidTokens, $aWordFrequencyScores, $bStructuredPhrases)
		{
			/*
			   Calculate all searches using aValidTokens i.e.
			   'Wodsworth Road, Sheffield' =>

			   Phrase Wordset
			   0      0       (wodsworth road)
			   0      1       (wodsworth)(road)
			   1      0       (sheffield)

			   Score how good the search is so they can be ordered
			 */
			foreach($aPhrases as $iPhrase => $sPhrase)
			{
				$aNewPhraseSearches = array();
				if ($bStructuredPhrases) $sPhraseType = $aPhraseTypes[$iPhrase];
				else $sPhraseType = '';

				foreach($aPhrases[$iPhrase]['wordsets'] as $iWordSet => $aWordset)
				{
					// Too many permutations - too expensive
					if ($iWordSet > 120) break;

					$aWordsetSearches = $aSearches;

					// Add all words from this wordset
					foreach($aWordset as $iToken => $sToken)
					{
						//echo "<br><b>$sToken</b>";
						$aNewWordsetSearches = array();

						foreach($aWordsetSearches as $aCurrentSearch)
						{
							//echo "<i>";
							//var_dump($aCurrentSearch);
							//echo "</i>";


								foreach($aValidTokens[' '.$sToken] as $aSearchTerm)
								{
									$aSearch = $aCurrentSearch;
									$aSearch['iSearchRank']++;
									if (($sPhraseType == '' || $sPhraseType == 'country') && !empty($aSearchTerm['country_code']) && $aSearchTerm['country_code'] != '0')
									{
										if ($aSearch['sCountryCode'] === false)
										{
											$aSearch['sCountryCode'] = strtolower($aSearchTerm['country_code']);
											// Country is almost always at the end of the string - increase score for finding it anywhere else (optimisation)
											if (($iToken+1 != sizeof($aWordset) || $iPhrase+1 != sizeof($aPhrases)))
											{
												$aSearch['iSearchRank'] += 5;
											}
											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
										}
									}
									elseif (isset($aSearchTerm['lat']) && $aSearchTerm['lat'] !== '' && $aSearchTerm['lat'] !== null)
									{
										if ($aSearch['fLat'] === '')
										{
											$aSearch['fLat'] = $aSearchTerm['lat'];
											$aSearch['fLon'] = $aSearchTerm['lon'];
											$aSearch['fRadius'] = $aSearchTerm['radius'];
											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
										}
									}
									elseif ($sPhraseType == 'postalcode')
									{
										// We need to try the case where the postal code is the primary element (i.e. no way to tell if it is (postalcode, city) OR (city, postalcode) so try both
										if (isset($aSearchTerm['word_id']) && $aSearchTerm['word_id'])
										{
											// If we already have a name try putting the postcode first
											if (sizeof($aSearch['aName']))
											{
												$aNewSearch = $aSearch;
												$aNewSearch['aAddress'] = array_merge($aNewSearch['aAddress'], $aNewSearch['aName']);
												$aNewSearch['aName'] = array();
												$aNewSearch['aName'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aNewSearch;
											}

											if (sizeof($aSearch['aName']))
											{
												if ((!$bStructuredPhrases || $iPhrase > 0) && $sPhraseType != 'country' && (!isset($aValidTokens[$sToken]) || strpos($sToken, ' ') !== false))
												{
													$aSearch['aAddress'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												}
												else
												{
													$aCurrentSearch['aFullNameAddress'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
													$aSearch['iSearchRank'] += 1000; // skip;
												}
											}
											else
											{
												$aSearch['aName'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												//$aSearch['iNamePhrase'] = $iPhrase;
											}
											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
										}

									}
									elseif (($sPhraseType == '' || $sPhraseType == 'street') && $aSearchTerm['class'] == 'place' && $aSearchTerm['type'] == 'house')
									{
										if ($aSearch['sHouseNumber'] === '')
										{
											$aSearch['sHouseNumber'] = $sToken;
											// sanity check: if the housenumber is not mainly made
											// up of numbers, add a penalty
											if (preg_match_all("/[^0-9]/", $sToken, $aMatches) > 2) $aSearch['iSearchRank']++;
											// also housenumbers should appear in the first or second phrase
											if ($iPhrase > 1) $aSearch['iSearchRank'] += 1;
											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
											/*
											// Fall back to not searching for this item (better than nothing)
											$aSearch = $aCurrentSearch;
											$aSearch['iSearchRank'] += 1;
											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
											 */
										}
									}
									elseif ($sPhraseType == '' && $aSearchTerm['class'] !== '' && $aSearchTerm['class'] !== null)
									{
										if ($aSearch['sClass'] === '')
										{
											$aSearch['sOperator'] = $aSearchTerm['operator'];
											$aSearch['sClass'] = $aSearchTerm['class'];
											$aSearch['sType'] = $aSearchTerm['type'];
											if (sizeof($aSearch['aName'])) $aSearch['sOperator'] = 'name';
											else $aSearch['sOperator'] = 'near'; // near = in for the moment
											if (strlen($aSearchTerm['operator']) == 0) $aSearch['iSearchRank'] += 1;

											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
										}
									}
									elseif (isset($aSearchTerm['word_id']) && $aSearchTerm['word_id'])
									{
										if (sizeof($aSearch['aName']))
										{
											if ((!$bStructuredPhrases || $iPhrase > 0) && $sPhraseType != 'country' && (!isset($aValidTokens[$sToken]) || strpos($sToken, ' ') !== false))
											{
												$aSearch['aAddress'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
											}
											else
											{
												$aCurrentSearch['aFullNameAddress'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												$aSearch['iSearchRank'] += 1000; // skip;
											}
										}
										else
										{
											$aSearch['aName'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
											//$aSearch['iNamePhrase'] = $iPhrase;
										}
										if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
									}
								}
							// Look for partial matches.
							// Note that there is no point in adding country terms here
							// because country are omitted in the address.
							if ($sPhraseType != 'country')
							{
								// Allow searching for a word - but at extra cost
								foreach($aValidTokens[$sToken] as $aSearchTerm)
								{
									if (isset($aSearchTerm['word_id']) && $aSearchTerm['word_id'])
									{
										if ((!$bStructuredPhrases || $iPhrase > 0) && sizeof($aCurrentSearch['aName']) && strpos($sToken, ' ') === false)
										{
											$aSearch = $aCurrentSearch;
											$aSearch['iSearchRank'] += 1;
											if ($aWordFrequencyScores[$aSearchTerm['word_id']] < CONST_Max_Word_Frequency)
											{
												$aSearch['aAddress'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
											}
											elseif (isset($aValidTokens[' '.$sToken])) // revert to the token version?
											{
												$aSearch['aAddressNonSearch'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												$aSearch['iSearchRank'] += 1;
												if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
												foreach($aValidTokens[' '.$sToken] as $aSearchTermToken)
												{
													if (empty($aSearchTermToken['country_code'])
															&& empty($aSearchTermToken['lat'])
															&& empty($aSearchTermToken['class']))
													{
														$aSearch = $aCurrentSearch;
														$aSearch['iSearchRank'] += 1;
														$aSearch['aAddress'][$aSearchTermToken['word_id']] = $aSearchTermToken['word_id'];
														if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
													}
												}
											}
											else
											{
												$aSearch['aAddressNonSearch'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
												if (preg_match('#^[0-9]+$#', $sToken)) $aSearch['iSearchRank'] += 2;
												if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
											}
										}

										if (!sizeof($aCurrentSearch['aName']) || $aCurrentSearch['iNamePhrase'] == $iPhrase)
										{
											$aSearch = $aCurrentSearch;
											$aSearch['iSearchRank'] += 1;
											if (!sizeof($aCurrentSearch['aName'])) $aSearch['iSearchRank'] += 1;
											if (preg_match('#^[0-9]+$#', $sToken)) $aSearch['iSearchRank'] += 2;
											if ($aWordFrequencyScores[$aSearchTerm['word_id']] < CONST_Max_Word_Frequency)
												$aSearch['aName'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
											else
												$aSearch['aNameNonSearch'][$aSearchTerm['word_id']] = $aSearchTerm['word_id'];
											$aSearch['iNamePhrase'] = $iPhrase;
											if ($aSearch['iSearchRank'] < $this->iMaxRank) $aNewWordsetSearches[] = $aSearch;
										}
									}
								}
							}
							else
							{
								// Allow skipping a word - but at EXTREAM cost
								//$aSearch = $aCurrentSearch;
								//$aSearch['iSearchRank']+=100;
								//$aNewWordsetSearches[] = $aSearch;
							}
						}
						// Sort and cut
						usort($aNewWordsetSearches, 'bySearchRank');
						$aWordsetSearches = array_slice($aNewWordsetSearches, 0, 50);
					}
					//var_Dump('<hr>',sizeof($aWordsetSearches)); exit;

					$aNewPhraseSearches = array_merge($aNewPhraseSearches, $aNewWordsetSearches);
					usort($aNewPhraseSearches, 'bySearchRank');

					$aSearchHash = array();
					foreach($aNewPhraseSearches as $iSearch => $aSearch)
					{
						$sHash = serialize($aSearch);
						if (isset($aSearchHash[$sHash])) unset($aNewPhraseSearches[$iSearch]);
						else $aSearchHash[$sHash] = 1;
					}

					$aNewPhraseSearches = array_slice($aNewPhraseSearches, 0, 50);
				}

				// Re-group the searches by their score, junk anything over 20 as just not worth trying
				$aGroupedSearches = array();
				foreach($aNewPhraseSearches as $aSearch)
				{
					if ($aSearch['iSearchRank'] < $this->iMaxRank)
					{
						if (!isset($aGroupedSearches[$aSearch['iSearchRank']])) $aGroupedSearches[$aSearch['iSearchRank']] = array();
						$aGroupedSearches[$aSearch['iSearchRank']][] = $aSearch;
					}
				}
				ksort($aGroupedSearches);

				$iSearchCount = 0;
				$aSearches = array();
				foreach($aGroupedSearches as $iScore => $aNewSearches)
				{
					$iSearchCount += sizeof($aNewSearches);
					$aSearches = array_merge($aSearches, $aNewSearches);
					if ($iSearchCount > 50) break;
				}

				//if (CONST_Debug) _debugDumpGroupedSearches($aGroupedSearches, $aValidTokens);

			}
			return $aGroupedSearches;

		}

		/* Perform the actual query lookup.

			Returns an ordered list of results, each with the following fields:
			  osm_type: type of corresponding OSM object
							N - node
							W - way
							R - relation
							P - postcode (internally computed)
			  osm_id: id of corresponding OSM object
			  class: general object class (corresponds to tag key of primary OSM tag)
			  type: subclass of object (corresponds to tag value of primary OSM tag)
			  admin_level: see http://wiki.openstreetmap.org/wiki/Admin_level
			  rank_search: rank in search hierarchy
							(see also http://wiki.openstreetmap.org/wiki/Nominatim/Development_overview#Country_to_street_level)
			  rank_address: rank in address hierarchy (determines orer in address)
			  place_id: internal key (may differ between different instances)
			  country_code: ISO country code
			  langaddress: localized full address
			  placename: localized name of object
			  ref: content of ref tag (if available)
			  lon: longitude
			  lat: latitude
			  importance: importance of place based on Wikipedia link count
			  addressimportance: cumulated importance of address elements
			  extra_place: type of place (for admin boundaries, if there is a place tag)
			  aBoundingBox: bounding Box
			  label: short description of the object class/type (English only) 
			  name: full name (currently the same as langaddress)
			  foundorder: secondary ordering for places with same importance
		*/
		function lookup()
		{
			if (!$this->sQuery && !$this->aStructuredQuery) return false;

            $sQuery = $this->sQuery;

            // Split query into phrases
            // Commas are used to reduce the search space by indicating where phrases split
            if ($this->aStructuredQuery)
            {
                $aPhrases = $this->aStructuredQuery;
                $bStructuredPhrases = true;
            }
            else
            {
                $aPhrases = explode(',',$sQuery);
                $bStructuredPhrases = false;
            }

            $aTokens = array();
            foreach($aPhrases as $iPhrase => $sPhrase)
            {
                $aPhrase = $this->oDB->getRow("select make_standard_name('".pg_escape_string($sPhrase)."') as string");
                if (PEAR::isError($aPhrase))
                {
                    userError("Illegal query string (not an UTF-8 string): ".$sPhrase);
                    if (CONST_Debug) var_dump($aPhrase);
                    exit;
                }
                if (trim($aPhrase['string']))
                {
                    $aPhrases[$iPhrase] = $aPhrase;
                    $aPhrases[$iPhrase]['words'] = explode(' ',$aPhrases[$iPhrase]['string']);
                    $aPhrases[$iPhrase]['wordsets'] = getWordSets($aPhrases[$iPhrase]['words'], 0);
                    $aTokens = array_merge($aTokens, getTokensFromSets($aPhrases[$iPhrase]['wordsets']));
                }
                else
                {
                    unset($aPhrases[$iPhrase]);
                }
            }

            // Reindex phrases - we make assumptions later on that they are numerically keyed in order
            $aPhraseTypes = array_keys($aPhrases);
            $aPhrases = array_values($aPhrases);

            $sSQL = 'select word_id,word_token, word, class, type, country_code, operator, search_name_count';
            $sSQL .= " from word where word_token like '".$aPhrases[0]["string"]."%'";

            $aDatabaseWords = $this->oDB->getAll($sSQL);

            $whereClaus = "";
            $i = 0;
            foreach ($aDatabaseWords as $word)
            {
                if($i==0)
                {
                    $whereClaus.= " name_vector @> ARRAY[".$word[word_id]."] ";
                }
                else{
                    $whereClaus.= " OR name_vector @> ARRAY[".$word[word_id]."] ";
                }

                $i++;
            }

            //$aResultPlaceIDs = $this->oDB->getCol("select place_id, 0::int as exactmatch from search_name where name_vector @> ARRAY[102979] order by (case when importance = 0 OR importance IS NULL then 0.75-(search_rank::float/40) else importance end) DESC limit 20");
            $aResultPlaceIDs = $this->oDB->getCol("select place_id, 0::int as exactmatch from search_name where".$whereClaus." order by (case when importance = 0 OR importance IS NULL then 0.75-(search_rank::float/40) else importance end) DESC limit 20");

            // Did we find anything?
            if (isset($aResultPlaceIDs) && sizeof($aResultPlaceIDs))
            {
                $aSearchResults = $this->getDetails($aResultPlaceIDs);
            }


			// No results? Done
			if (!sizeof($aSearchResults))
			{
				if ($this->bFallback)
				{
					if ($this->fallbackStructuredQuery())
					{
						return $this->lookup();
					}
				}

				return array();
			}

			$aClassType = getClassTypesWithImportance();
			$aRecheckWords = preg_split('/\b[\s,\\-]*/u',$sQuery);
			foreach($aRecheckWords as $i => $sWord)
			{
				if (!preg_match('/\pL/', $sWord)) unset($aRecheckWords[$i]);
			}

            if (CONST_Debug) { echo '<i>Recheck words:<\i>'; var_dump($aRecheckWords); }

			foreach($aSearchResults as $iResNum => $aResult)
			{
				// Default
				$fDiameter = 0.0001;

				if (isset($aClassType[$aResult['class'].':'.$aResult['type'].':'.$aResult['admin_level']]['defdiameter'])
						&& $aClassType[$aResult['class'].':'.$aResult['type'].':'.$aResult['admin_level']]['defdiameter'])
				{
					$fDiameter = $aClassType[$aResult['class'].':'.$aResult['type'].':'.$aResult['admin_level']]['defdiameter'];
				}
				elseif (isset($aClassType[$aResult['class'].':'.$aResult['type']]['defdiameter'])
						&& $aClassType[$aResult['class'].':'.$aResult['type']]['defdiameter'])
				{
					$fDiameter = $aClassType[$aResult['class'].':'.$aResult['type']]['defdiameter'];
				}
				$fRadius = $fDiameter / 2;

				if (CONST_Search_AreaPolygons)
				{
					// Get the bounding box and outline polygon
					$sSQL = "select place_id,0 as numfeatures,st_area(geometry) as area,";
					$sSQL .= "ST_Y(centroid) as centrelat,ST_X(centroid) as centrelon,";
					$sSQL .= "ST_YMin(geometry) as minlat,ST_YMax(geometry) as maxlat,";
					$sSQL .= "ST_XMin(geometry) as minlon,ST_XMax(geometry) as maxlon";
					if ($this->bIncludePolygonAsGeoJSON) $sSQL .= ",ST_AsGeoJSON(geometry) as asgeojson";
					if ($this->bIncludePolygonAsKML) $sSQL .= ",ST_AsKML(geometry) as askml";
					if ($this->bIncludePolygonAsSVG) $sSQL .= ",ST_AsSVG(geometry) as assvg";
					if ($this->bIncludePolygonAsText || $this->bIncludePolygonAsPoints) $sSQL .= ",ST_AsText(geometry) as astext";
					$sFrom = " from placex where place_id = ".$aResult['place_id'];
					if ($this->fPolygonSimplificationThreshold > 0)
					{
						$sSQL .= " from (select place_id,centroid,ST_SimplifyPreserveTopology(geometry,".$this->fPolygonSimplificationThreshold.") as geometry".$sFrom.") as plx";
					}
					else
					{
						$sSQL .= $sFrom;
					}

					$aPointPolygon = $this->oDB->getRow($sSQL);
					if (PEAR::IsError($aPointPolygon))
					{
						failInternalError("Could not get outline.", $sSQL, $aPointPolygon);
					}

					if ($aPointPolygon['place_id'])
					{
						if ($this->bIncludePolygonAsGeoJSON) $aResult['asgeojson'] = $aPointPolygon['asgeojson'];
						if ($this->bIncludePolygonAsKML) $aResult['askml'] = $aPointPolygon['askml'];
						if ($this->bIncludePolygonAsSVG) $aResult['assvg'] = $aPointPolygon['assvg'];
						if ($this->bIncludePolygonAsText) $aResult['astext'] = $aPointPolygon['astext'];

						if ($aPointPolygon['centrelon'] !== null && $aPointPolygon['centrelat'] !== null )
						{
							$aResult['lat'] = $aPointPolygon['centrelat'];
							$aResult['lon'] = $aPointPolygon['centrelon'];
						}

						if ($this->bIncludePolygonAsPoints)
						{
							// Translate geometry string to point array
							if (preg_match('#POLYGON\\(\\(([- 0-9.,]+)#',$aPointPolygon['astext'],$aMatch))
							{
								preg_match_all('/(-?[0-9.]+) (-?[0-9.]+)/',$aMatch[1],$aPolyPoints,PREG_SET_ORDER);
							}
							elseif (preg_match('#MULTIPOLYGON\\(\\(\\(([- 0-9.,]+)#',$aPointPolygon['astext'],$aMatch))
							{
								preg_match_all('/(-?[0-9.]+) (-?[0-9.]+)/',$aMatch[1],$aPolyPoints,PREG_SET_ORDER);
							}
							elseif (preg_match('#POINT\\((-?[0-9.]+) (-?[0-9.]+)\\)#',$aPointPolygon['astext'],$aMatch))
							{
								$iSteps = max(8, min(100, ($fRadius * 40000)^2));
								$fStepSize = (2*pi())/$iSteps;
								$aPolyPoints = array();
								for($f = 0; $f < 2*pi(); $f += $fStepSize)
								{
									$aPolyPoints[] = array('',$aMatch[1]+($fRadius*sin($f)),$aMatch[2]+($fRadius*cos($f)));
								}
							}
						}

						// Output data suitable for display (points and a bounding box)
						if ($this->bIncludePolygonAsPoints && isset($aPolyPoints))
						{
							$aResult['aPolyPoints'] = array();
							foreach($aPolyPoints as $aPoint)
							{
								$aResult['aPolyPoints'][] = array($aPoint[1], $aPoint[2]);
							}
						}

						if (abs($aPointPolygon['minlat'] - $aPointPolygon['maxlat']) < 0.0000001)
						{
							$aPointPolygon['minlat'] = $aPointPolygon['minlat'] - $fRadius;
							$aPointPolygon['maxlat'] = $aPointPolygon['maxlat'] + $fRadius;
						}
						if (abs($aPointPolygon['minlon'] - $aPointPolygon['maxlon']) < 0.0000001)
						{
							$aPointPolygon['minlon'] = $aPointPolygon['minlon'] - $fRadius;
							$aPointPolygon['maxlon'] = $aPointPolygon['maxlon'] + $fRadius;
						}
						$aResult['aBoundingBox'] = array((string)$aPointPolygon['minlat'],(string)$aPointPolygon['maxlat'],(string)$aPointPolygon['minlon'],(string)$aPointPolygon['maxlon']);
					}
				}

				if ($aResult['extra_place'] == 'city')
				{
					$aResult['class'] = 'place';
					$aResult['type'] = 'city';
					$aResult['rank_search'] = 16;
				}

				if (!isset($aResult['aBoundingBox']))
				{
					$iSteps = max(8,min(100,$fRadius * 3.14 * 100000));
					$fStepSize = (2*pi())/$iSteps;
					$aPointPolygon['minlat'] = $aResult['lat'] - $fRadius;
					$aPointPolygon['maxlat'] = $aResult['lat'] + $fRadius;
					$aPointPolygon['minlon'] = $aResult['lon'] - $fRadius;
					$aPointPolygon['maxlon'] = $aResult['lon'] + $fRadius;

					// Output data suitable for display (points and a bounding box)
					if ($this->bIncludePolygonAsPoints)
					{
						$aPolyPoints = array();
						for($f = 0; $f < 2*pi(); $f += $fStepSize)
						{
							$aPolyPoints[] = array('',$aResult['lon']+($fRadius*sin($f)),$aResult['lat']+($fRadius*cos($f)));
						}
						$aResult['aPolyPoints'] = array();
						foreach($aPolyPoints as $aPoint)
						{
							$aResult['aPolyPoints'][] = array($aPoint[1], $aPoint[2]);
						}
					}
					$aResult['aBoundingBox'] = array((string)$aPointPolygon['minlat'],(string)$aPointPolygon['maxlat'],(string)$aPointPolygon['minlon'],(string)$aPointPolygon['maxlon']);
				}

				// Is there an icon set for this type of result?
				if (isset($aClassType[$aResult['class'].':'.$aResult['type']]['icon'])
						&& $aClassType[$aResult['class'].':'.$aResult['type']]['icon'])
				{
					$aResult['icon'] = CONST_Website_BaseURL.'images/mapicons/'.$aClassType[$aResult['class'].':'.$aResult['type']]['icon'].'.p.20.png';
				}

				if (isset($aClassType[$aResult['class'].':'.$aResult['type'].':'.$aResult['admin_level']]['label'])
						&& $aClassType[$aResult['class'].':'.$aResult['type'].':'.$aResult['admin_level']]['label'])
				{
					$aResult['label'] = $aClassType[$aResult['class'].':'.$aResult['type'].':'.$aResult['admin_level']]['label'];
				}
				elseif (isset($aClassType[$aResult['class'].':'.$aResult['type']]['label'])
						&& $aClassType[$aResult['class'].':'.$aResult['type']]['label'])
				{
					$aResult['label'] = $aClassType[$aResult['class'].':'.$aResult['type']]['label'];
				}

				if ($this->bIncludeAddressDetails)
				{
					$aResult['address'] = getAddressDetails($this->oDB, $sLanguagePrefArraySQL, $aResult['place_id'], $aResult['country_code']);
					if ($aResult['extra_place'] == 'city' && !isset($aResult['address']['city']))
					{
						$aResult['address'] = array_merge(array('city' => array_shift(array_values($aResult['address']))), $aResult['address']);
					}
				}

				if ($this->bIncludeExtraTags)
				{
					if ($aResult['extra'])
					{
						$aResult['sExtraTags'] = json_decode($aResult['extra']);
					}
					else
					{
						$aResult['sExtraTags'] = (object) array();
					}
				}

				if ($this->bIncludeNameDetails)
				{
					if ($aResult['names'])
					{
						$aResult['sNameDetails'] = json_decode($aResult['names']);
					}
					else
					{
						$aResult['sNameDetails'] = (object) array();
					}
				}

				// Adjust importance for the number of exact string matches in the result
				$aResult['importance'] = max(0.001,$aResult['importance']);
				$iCountWords = 0;
				$sAddress = $aResult['langaddress'];
				foreach($aRecheckWords as $i => $sWord)
				{
					if (stripos($sAddress, $sWord)!==false)
					{
						$iCountWords++;
						if (preg_match("/(^|,)\s*".preg_quote($sWord, '/')."\s*(,|$)/", $sAddress)) $iCountWords += 0.1;
					}
				}

				$aResult['importance'] = $aResult['importance'] + ($iCountWords*0.1); // 0.1 is a completely arbitrary number but something in the range 0.1 to 0.5 would seem right

				$aResult['name'] = $aResult['langaddress'];
				// secondary ordering (for results with same importance (the smaller the better):
				//   - approximate importance of address parts
				$aResult['foundorder'] = -$aResult['addressimportance']/10;
				//   - number of exact matches from the query
				if (isset($this->exactMatchCache[$aResult['place_id']]))
					$aResult['foundorder'] -= $this->exactMatchCache[$aResult['place_id']];
				else if (isset($this->exactMatchCache[$aResult['parent_place_id']]))
					$aResult['foundorder'] -= $this->exactMatchCache[$aResult['parent_place_id']];
				//  - importance of the class/type
				if (isset($aClassType[$aResult['class'].':'.$aResult['type']]['importance'])
					&& $aClassType[$aResult['class'].':'.$aResult['type']]['importance'])
				{
					$aResult['foundorder'] += 0.0001 * $aClassType[$aResult['class'].':'.$aResult['type']]['importance'];
				}
				else
				{
					$aResult['foundorder'] += 0.01;
				}
				if (CONST_Debug) { var_dump($aResult); }
				$aSearchResults[$iResNum] = $aResult;
			}
			uasort($aSearchResults, 'byImportance');

			$aOSMIDDone = array();
			$aClassTypeNameDone = array();
			$aToFilter = $aSearchResults;
			$aSearchResults = array();

			$bFirst = true;
			foreach($aToFilter as $iResNum => $aResult)
			{
				$this->aExcludePlaceIDs[$aResult['place_id']] = $aResult['place_id'];
				if ($bFirst)
				{
					$fLat = $aResult['lat'];
					$fLon = $aResult['lon'];
					if (isset($aResult['zoom'])) $iZoom = $aResult['zoom'];
					$bFirst = false;
				}
				if (!$this->bDeDupe || (!isset($aOSMIDDone[$aResult['osm_type'].$aResult['osm_id']])
							&& !isset($aClassTypeNameDone[$aResult['osm_type'].$aResult['class'].$aResult['type'].$aResult['name'].$aResult['admin_level']])))
				{
					$aOSMIDDone[$aResult['osm_type'].$aResult['osm_id']] = true;
					$aClassTypeNameDone[$aResult['osm_type'].$aResult['class'].$aResult['type'].$aResult['name'].$aResult['admin_level']] = true;
					$aSearchResults[] = $aResult;
				}

				// Absolute limit on number of results
				if (sizeof($aSearchResults) >= $this->iFinalLimit) break;
			}

			return $aSearchResults;

		} // end lookup()


	} // end class

