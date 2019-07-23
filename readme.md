
Example:

```php
    require_once 'vendor/autoload.php';
    
    class JSONPlaceholder extends \JsonAPI\Model {
        use \JsonAPI\Resource;
    
        protected $endpoint = 'https://jsonplaceholder.typicode.com/todos';
    
        public static function FromArray(array $data)
        {
            $object = new self;
    
            // @todo Schema validation here
            $object->userId = $data['userId'];
            $object->id = $data['id'];
            $object->title = $data['title'];
    
            return $object;
        }
    
        /**
         * Get object by ID
         *
         * @param $id
         * @return JSONPlaceholder
         * @throws \JsonAPI\Client\Exception
         */
        public function get($id)
        {
            return $this->request($id, 'GET', [], self::class);
        }
    
        public function getId() : string
        {
            return $this->id;
        }
    
        public function getUserId() : string
        {
            return $this->userId;
        }
    
        public function getTitle() : string
        {
            return $this->title;
        }
    }
    
    
    $test = new JSONPlaceholder;
    $object = $test->setAdapter(new \JsonAPI\Client\Adapter\Curl())->get(1);
    
    echo $object->getId().' - '.$object->getTitle(); // will echo '1 - delectus aut autem'
```