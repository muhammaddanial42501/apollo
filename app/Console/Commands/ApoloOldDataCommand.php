<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApoloOldDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:old-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Running Command');

        //$fp = fopen('data - Copy.csv', 'a+');
        $fp1 = fopen('good-data-1.csv', 'a+');

        $rows = array_map('str_getcsv', file('data - Copy.csv'));
        $header = array_shift($rows);
        $csv = [];

        foreach ($rows as $row) {
            $csv[] = array_combine($header, $row);
        }

        $data = [];

        foreach ($csv as $people) {
            $email = $people['email'] ?? null;
            $name = $people['name'] ?? null;
            $title = $people['title'] ?? '';
            $companyName = $people['company'] ?? null;
            $orgId = $companyName;

            if (blank($email) || blank($companyName)) {
                continue;
            }

            $peopleArray = $data[$orgId] ?? [];
            $good = count($peopleArray) < 3;

            if ($good) {
                $ceoRegex = '/ceo|chief executive officer/i';
                $ceoOtherRegex = '/president|owner|chairman|founder/i';
                $cfoRegex = '/cfo|chief financial officer/i';
                $cfoOtherRegex = '/payable|controller|accountant|invoice|payroll/i';

                if (preg_match($ceoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $ceoRegex) && ! $this->matchedPreviousTitle($peopleArray, $ceoOtherRegex)) {
                    $good = true;
                    $this->info('1');
                } else if (! preg_match($ceoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $ceoRegex) && preg_match($ceoOtherRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $ceoOtherRegex)) {
                    $good = true;
                    $this->info('2');
                } else if (preg_match($cfoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $cfoRegex) && ! $this->matchedPreviousTitle($peopleArray, $cfoOtherRegex)) {
                    $good = true;
                    $this->info('3');
                } else if (! preg_match($cfoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $cfoRegex) && preg_match($cfoOtherRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $cfoOtherRegex)) {
                    $good = true;
                    $this->info('4');
                } else {
                    $good = false;
                }
            }

            if ($good) {
                $data[$orgId][] = [
                    'name' => $name,
                    'email' => $email,
                    'title' => $title,
                    'companyName' => $companyName,
                ];
            } else {
                $this->warn('no matched');
            }
        }

        foreach ($data as $people) {
            if (count($people) == 2) {
                fputcsv($fp1, [
                    $people[0]['companyName'],
                    $people[0]['name'],
                    $people[0]['email'],
                    $people[0]['title'],
                    $people[1]['name'],
                    $people[1]['email'],
                    $people[1]['title'],
                ]);
            }
        }
    }

    private function matchedPreviousTitle($data, $match): bool
    {
        if (blank($data)) {
            return false;
        }

        $matched = false;

        foreach ($data as $datum) {
            if (preg_match($match, $datum['title'])) {
                $matched = true;
                break;
            }
        }

        return $matched;
    }
}
