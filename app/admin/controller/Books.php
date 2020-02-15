<?php


namespace app\admin\controller;


use app\model\Author;
use app\model\Book;
use app\model\Cate;
use app\model\Chapter;
use app\service\AuthorService;
use app\service\BookService;
use Overtrue\Pinyin\Pinyin;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\View;

class Books extends Base
{
    protected $authorService;
    protected $bookService;

    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->authorService = new AuthorService();
        $this->bookService = new BookService();
    }

    public function index()
    {
        $data = $this->bookService->getPagedBooksAdmin(1);
        $books = $data['books'];
        foreach ($books as &$book) {
            $book['chapter_count'] = count($book->chapters);
        }
        $count = $data['count'];
        View::assign([
            'books' => $books,
            'count' => $count
        ]);
        return view();
    }

    public function create()
    {
        if (request()->isPost()) {
            $book = new Book();
            $data = $this->request->param();

            //作者处理
            try {
                $author = Author::where('author_name', '=', $data['author'])->findOrFail();
            } catch (DataNotFoundException $e) {
            } catch (ModelNotFoundException $e) {
                $author = new Author();
                $author->author_name = $data['author'];
                $author->save();
            }

            $book->author_id = $author->id;
            $book->author_name = $author->author_name;
            $book->last_time = time();
            $str = $this->convert($data['book_name']); //生成标识

            $c = (int)Book::where('unique_id','=',$str)->count();
            if ($c > 0) {
                $data['unique_id'] = md5(time() . mt_rand(1,1000000)); //如果已经存在相同标识，则生成一个新的随机标识
            } else {
                $data['unique_id'] = $str;
            }


            $dir = 'book/cover';
            if (request()->file() != null) {
                $cover = request()->file('cover');
                try {
                    validate(['image'=>'filesize:10240|fileExt:jpg,png,gif'])
                        ->check((array)$cover);
                    $savename =str_replace ( '\\', '/',
                        \think\facade\Filesystem::disk('public')->putFile($dir, $cover));
                    if (!is_null($savename)) {
                        $book->cover_url = '/static/upload/'.$savename;
                    }
                } catch (ValidateException $e) {
                    abort(404, $e->getMessage());
                }
            }

            $result = $book->save($data);
            if ($result) {
                $this->success('添加成功','index',1);
            } else {
                throw new ValidateException('添加失败');
            }
        }
        $cates = Cate::select();
        View::assign('cates', $cates);
        return view();
    }

    public function edit()
    {
        $cates = Cate::select();
        $data = request()->param();
        try {
            $book = Book::findOrFail($data['id']);
            if (request()->isPost()) {
                //作者处理
                try {
                    $author = Author::where('author_name', '=', $data['author'])->findOrFail();
                } catch (DataNotFoundException $e) {
                } catch (ModelNotFoundException $e) {
                    $author = new Author();
                    $author->author_name = $data['author'];
                    $author->save();
                }

                $book->author_id = $author->id;
                $book->author_name = $author->author_name;
                $c = (int)Book::where('unique_id','=',$data['unique_id'])->count();
                if ($c > 0) {
                    $book->unique_id = md5(time() . mt_rand(1,1000000)); //如果已经存在相同标识，则生成一个新的随机标识
                } else {
                    $book->unique_id = $data['unique_id'];
                }
                $book->last_time = time();
                $dir = 'book/cover';
                if (request()->file() != null) {
                    $cover = request()->file('cover');
                    try {
                        validate(['image'=>'filesize:10240|fileExt:jpg,png,gif'])
                            ->check((array)$cover);
                        $savename =str_replace ( '\\', '/',
                            \think\facade\Filesystem::disk('public')->putFile($dir, $cover));
                        if (!is_null($savename)) {
                            $book->cover_url = '/static/upload/'.$savename;
                        }
                    } catch (ValidateException $e) {
                        throw new ValidateException($e->getMessage());
                    }
                }

                $result = $book->save($data);
                if ($result) {
                    $this->success('编辑成功');
                } else {
                    throw new ValidateException('修改失败');
                }
            }
            View::assign([
                'book' => $book,
                'cates' => $cates
            ]);
        } catch (DataNotFoundException $e) {
            abort(404, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, $e->getMessage());
        }
        return view();
    }

    public function search()
    {
        $name = input('book_name');
        $status = input('status');
        $where = [
            ['book_name', 'like', '%' . $name . '%']
        ];
        $data = $this->bookService->getPagedBooksAdmin($status,$where);
        $books = $data['books'];
        foreach ($books as &$book) {
            $book['chapter_count'] = count($book->chapters);
        }
        $count = $data['count'];
        View::assign([
            'books' => $books,
            'count' => $count
        ]);
        return view('index');
    }

    public function disable()
    {
        $id = input('id');
        if (empty($id) || is_null($id)) {
            return ['status' => 0];
        }
        try {
            $book = Book::findOrFail($id);
            $result = $book->delete();
            if ($result) {
                return json(['status' => 1]);
            } else {
                return json(['status' => 0]);
            }
        } catch (DataNotFoundException $e) {
            abort(404, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, $e->getMessage());
        }
    }

    public function enable()
    {
        $id = input('id');
        if (empty($id) || is_null($id)) {
            return ['status' => 0];
        }
        try {
            $book = Book::onlyTrashed()->findOrFail($id);
            $result = $book->restore();
            if ($result) {
                return json(['status' => 1]);
            } else {
                return json(['status' => 0]);
            }
        } catch (DataNotFoundException $e) {
            abort(404, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, $e->getMessage());
        }
    }

    public function disabled()
    {
        $data = $this->bookService->getPagedBooksAdmin(0);
        $books = $data['books'];
        foreach ($books as &$book) {
            $book['chapter_count'] = count($book->chapters);
        }
        $count = $data['count'];
        View::assign([
            'books' => $books,
            'count' => $count
        ]);
        return view();
    }

    public function delete()
    {
        $id = input('id');
        try {
            $book = Book::withTrashed()->findOrFail($id);
            $chapters = Chapter::where('book_id', '=', $id)->select(); //按漫画id查找所有章节
            foreach ($chapters as $chapter) {
                $chapter->delete(); //删除章节
            }
            $result = $book->delete(true);
            if ($result) {
                return json(['err' => 0, 'msg' => '删除成功']);
            } else {
                return json(['err' => 1, 'msg' => '删除失败']);
            }

        } catch (DataNotFoundException $e) {
            abort(404, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, $e->getMessage());
        }
    }

    public function deleteAll()
    {
        $ids = input('ids');
        foreach ($ids as $id) {
            $chapters = Chapter::where('book_id', '=', $id)->select(); //按漫画id查找所有章节
            foreach ($chapters as $chapter) {
                $chapter->delete(); //删除章节
            }
        }
        Book::destroy($ids,true);
    }

    protected function convert($str){
        $pinyin = new Pinyin();
        $name_format = config('seo.name_format');
        switch ($name_format) {
            case 'pure':
                $str = $pinyin->permalink($str, '');
                //$str = implode($arr,'');
                break;
            case 'abbr':
                $str = $pinyin->abbr($str);break;
            default:
                $str = $pinyin->permalink($str, '');break;
        }
        return $str;
    }

}