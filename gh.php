<?php

class GitExtractor
{
    public function loadData()
    {
        shell_exec("git fetch");

        $branches = shell_exec('git branch -a | grep remote');
        $data = array_map(fn($branch) => str_replace("  remotes/origin/", "", $branch), explode("\n", $branches));
        $dataAfter = array_filter($data, fn($branch) => !(str_contains($branch, "HEAD") || trim($branch) == ''));
        $branchesToDelete = ['NO-PR' => []];
        foreach ($dataAfter as $branchName) {
            $branchPRInfo = shell_exec("gh pr view {$branchName} --json  state,isDraft,updatedAt,createdAt 2>/dev/null");
            if (!$branchPRInfo) {
                $branchesToDelete['NO-PR'][] = $branchName;
            } else {
                $branchPRInfo = json_decode($branchPRInfo, true, 512, JSON_THROW_ON_ERROR);
                $statePure = str_replace(['"', "\n"], '', $branchPRInfo['state']);
                if (!array_key_exists($statePure, $branchesToDelete)) {
                    $branchesToDelete[$statePure] = [];
                }
                if ($statePure === 'OPEN') {
                    $statePure = $branchPRInfo['isDraft'] ? 'DRAFT' : 'OPEN';
                }
                $branchesToDelete[$statePure][] = ['name' => $branchName, 'updatedAt' => $branchPRInfo['updatedAt']];
            }
        }
        $results = [];
        foreach ($branchesToDelete as $type => $branches) {
            $results[$type] = count($branches);
        }
        $results["TOTAL"] = array_reduce($results, fn($prev, $item) => $prev + $item);
        print_r($branchesToDelete);
        print_r($results);
        file_put_contents(__DIR__ . "/data.json", json_encode($branchesToDelete));
    }

    public function deleteBranches($branchBulk, $force = false)
    {
        $toDelete = [];
        foreach ($branchBulk as $closedBranchToDelete) {
            $date = new DateTime($closedBranchToDelete['updatedAt']);
            if ((int)$date->format('Y') < 2022) {
                if ($force) {
                    echo "DELETING {$closedBranchToDelete['name']}\n";
                    echo shell_exec("git push origin --delete {$closedBranchToDelete['name']}");
                }
//                echo "Wanna delete {$closedBranchToDelete['name']} - {$closedBranchToDelete['updatedAt']}\n";
                $toDelete[] = $closedBranchToDelete['name'];
            } else {
//                echo "Skipping {$closedBranchToDelete['name']} - {$closedBranchToDelete['updatedAt']}\n";
            }
        }

        print_r($toDelete);
    }

    public function getData($type)
    {
        $data = json_decode(file_get_contents(__DIR__ . "/data.json"), true);
        return $data[$type];
    }

    public function listNoPR($data)
    {
        $toDelete = [];
        foreach ($data as $branchName) {
            $date = shell_exec(sprintf('git log -n 1 origin/%s | grep Date', $branchName));
            $date = substr($date, 8);
            $dateTime = new Datetime($date);
            $year = $dateTime->format("Y");
            if ($year < 2022) {
                if (!array_key_exists($year, $toDelete)) {
                    $toDelete[$year] = [];
                }
                $toDelete[$year][] = $branchName;

            }
        }

        print_r($toDelete);
    }
}

$obj = new GitExtractor();
//$obj->loadData();
//echo "OPEN";
//$data = $obj->getData('OPEN');
//$obj->deleteBranches($data, false);


$data = $obj->getData("NO-PR");
$obj->listNoPR($data);
