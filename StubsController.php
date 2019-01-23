<?php

namespace bazilio\stubsgenerator;

use yii\console\Controller;
use yii\console\Exception;

class StubsController extends Controller
{
    public $outputFile = null;

    protected function getTemplate()
    {
        return <<<TPL
<?php

/**
 * Yii app stub file. Autogenerated by yii2-stubs-generator (stubs console command).
 * Used for enhanced IDE code autocompletion.
 * Updated on {time}
 */
class Yii extends \yii\BaseYii
{
    /**
     * @var BaseApplication|WebApplication|ConsoleApplication the application instance
     */
    public static \$app;
}
/**{stubs}
 **/
abstract class BaseApplication extends yii\base\Application
{
}

/**{stubs}
 **/
class WebApplication extends yii\web\Application
{
}

/**{stubs}
 **/
class ConsoleApplication extends yii\console\Application
{
}
TPL;
    }

    protected function getUserTemplate()
    {
        return <<<TPL

/**
 * @property {user_identities} \$identity
 */
class User extends \yii\web\User {
}
TPL;
    }


    public function actionIndex()
    {
        $path = $this->outputFile ? $this->outputFile :
            \Yii::$app->getVendorPath() . DIRECTORY_SEPARATOR . 'Yii.php';

        $components = [];
        $userIdentities = [];

        foreach (\Yii::$app->requestedParams as $configPath) {
            if (!file_exists($configPath)) {
                throw new Exception('Config file doesn\'t exists: ' . $configPath);
            }

            $config = include($configPath);

            if (empty($config['components'])) {
                continue;
            }

            foreach ($config['components'] as $name => $definition) {

                if (is_string($definition)) {
                    $class = $definition;
                } else {

                    if (is_callable($definition, true)) {
                        try{
                            $definition = \Yii::createObject($definition);
                        } catch(\Exception $exception){
                            continue;
                        }
                    }
                    if (is_object($definition) && !$definition instanceof Closure) {
                        $class = get_class($definition);
                    } else if (is_array($definition)) {

                        if ($name === 'user' && isset($definition['identityClass'])) {
                            $userIdentities[] = $definition['identityClass'];
                        }

                        if(isset($definition['class'])){
                            $class = $definition['class'];
                        } else {
                            continue;
                        }
                    }
                }
                $components[$name][] = $class;
            }
        }

        $stubs = '';
        $userStubs = '';

        if (sizeof($userIdentities)) {
            $components['user'][] = 'User';

            $userIdentities = implode('|', array_unique($userIdentities));
            $userStubs = str_replace(
                '{user_identities}',
                $userIdentities,
                $this->getUserTemplate()
            );
        }

        foreach ($components as $name => $classes) {
            $classes = implode('|', array_unique($classes));
            $stubs .= "\n * @property {$classes} \$$name";
        }

        $content = str_replace('{stubs}', $stubs, $this->getTemplate());
        $content = str_replace('{time}', date(DATE_ISO8601), $content);
        $content .= $userStubs;

        if ($content != @file_get_contents($path)) {
            file_put_contents($path, $content);
        }
    }
}
