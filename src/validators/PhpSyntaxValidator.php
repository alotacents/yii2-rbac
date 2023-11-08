<?php

namespace alotacents\rbac\validators;


use Yii;
use yii\validators\Validator;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class PhpSyntaxValidator extends Validator
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} "{value}" has already been taken.');
        }
    }

    public function validateValue($value)
    {

        $valid = true;
        if(!is_file($value) || !is_readable($value)){
            return ['invalid file', []];
            $valid = false;
        } else {

            $cwd = getcwd();

            $binary = (new PhpExecutableFinder())->find();

            $entry = Yii::$app->getRequest()->getScriptFile();

            $cwd = dirname($entry);
            $filename = basename($entry);

            $command = "{$binary} -l {$value}";

            //$command = "{$entry} report/long";

            $process = new Process($command, $cwd, null, null, null);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                //return [$output, []];
            } else {
                $valid = false;
                $output = $process->getOutput();
                $output = str_replace($value, basename($value, '.tmp'), $output);
                return [$output, []];
            }
        }

        return $valid ? null : [$this->message, []];

    }
}