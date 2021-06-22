<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use RuntimeException;

class TerritoryInfo extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'territoryInfo']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $data = parent::process($data);
        //http://www.unicode.org/reports/tr35/tr35-info.html#Supplemental_Territory_Information
        unset($data['ZZ']);
        foreach ($data as $territoryID => $territoryInfoList) {
            $finalTerritoryData = [];
            foreach ($territoryInfoList as $territoryInfoID => $territoryInfoData) {
                switch ($territoryInfoID) {
                    case '_gdp': // Gross domestic product
                        if (!$this->asNumber($territoryInfoData)) {
                            throw new RuntimeException("Unable to parse {$territoryInfoData} as a number ({$territoryInfoID})");
                        }
                        $finalTerritoryData['gdp'] = $territoryInfoData;
                        break;
                    case '_literacyPercent':
                        if (!$this->asNumber($territoryInfoData)) {
                            throw new RuntimeException("Unable to parse {$territoryInfoData} as a number ({$territoryInfoID})");
                        }
                        $finalTerritoryData['literacy'] = $territoryInfoData;
                        break;
                    case '_population':
                        if (!$this->asNumber($territoryInfoData)) {
                            throw new RuntimeException("Unable to parse {$territoryInfoData} as a number ({$territoryInfoID})");
                        }
                        $finalTerritoryData['population'] = $territoryInfoData;
                        break;
                    case 'languagePopulation':
                        if (!is_array($territoryInfoData)) {
                            throw new RuntimeException("Invalid node: {$territoryInfoID} is not an array");
                        }
                        $finalTerritoryData['languages'] = [];
                        foreach ($territoryInfoData as $languageID => $languageInfoList) {
                            if (!is_array($languageInfoList)) {
                                throw new RuntimeException("Invalid node: {$territoryInfoID}/{$languageID} is not an array");
                            }
                            $finalTerritoryData['languages'][$languageID] = [];
                            foreach ($languageInfoList as $languageInfoID => $languageInfoData) {
                                switch ($languageInfoID) {
                                    case '_officialStatus':
                                        switch ($languageInfoData) {
                                            case 'official':
                                                $v = 'o';
                                                break;
                                            case 'official_regional':
                                                $v = 'r';
                                                break;
                                            case 'de_facto_official':
                                                $v = 'f';
                                                break;
                                            case 'official_minority':
                                                $v = 'm';
                                                break;
                                            default:
                                                throw new RuntimeException("Unknown language status: {$languageInfoData}");
                                        }
                                        $finalTerritoryData['languages'][$languageID]['status'] = $v;
                                        break;
                                    case '_populationPercent':
                                        if (!$this->asNumber($languageInfoData)) {
                                            throw new RuntimeException("Unable to parse {$languageInfoData} as a number ({$territoryInfoID})");
                                        }
                                        $finalTerritoryData['languages'][$languageID]['population'] = $languageInfoData;
                                        break;
                                    case '_writingPercent':
                                        if (!$this->asNumber($languageInfoData)) {
                                            throw new RuntimeException("Unable to parse {$languageInfoData} as a number ({$territoryInfoID})");
                                        }
                                        $finalTerritoryData['languages'][$languageID]['writing'] = $languageInfoData;
                                        break;
                                    case '_literacyPercent':
                                        if (!$this->asNumber($languageInfoData)) {
                                            throw new RuntimeException("Unable to parse {$languageInfoData} as a number ({$territoryInfoID})");
                                        }
                                        $finalTerritoryData['languages'][$languageID]['literacy'] = $languageInfoData;
                                        break;
                                    default:
                                        throw new RuntimeException("Unknown node: {$territoryInfoID}/{$languageID}/{$languageInfoID}");
                                }
                            }
                            if (!array_key_exists('population', $finalTerritoryData['languages'][$languageID])) {
                                throw new RuntimeException("Missing _populationPercent node in for {$territoryID}/{$territoryInfoID}/{$languageID}");
                            }
                        }
                        if (empty($finalTerritoryData['languages'])) {
                            throw new RuntimeException("No languages for {$territoryID}");
                        }
                        break;
                    default:
                        throw new RuntimeException("Unknown node: {$territoryInfoID}");
                }
            }
            if (!array_key_exists('gdp', $finalTerritoryData)) {
                throw new RuntimeException("Missing _gdp node in for {$territoryID}");
            }
            if (!array_key_exists('literacy', $finalTerritoryData)) {
                throw new RuntimeException("Missing _literacyPercent node in for {$territoryID}");
            }
            if (!array_key_exists('population', $finalTerritoryData)) {
                throw new RuntimeException("Missing _population node in for {$territoryID}");
            }
            if (!array_key_exists('languages', $finalTerritoryData)) {
                throw new RuntimeException("Missing languagePopulation node in for {$territoryID}");
            }
            $data[$territoryID] = $finalTerritoryData;
        }

        return $data;
    }
}
