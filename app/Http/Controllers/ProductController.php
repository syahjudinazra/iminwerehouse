<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use DNS1D;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function products(Request $req){
        $sort   = $req->sort;
        $search = $req->q;
        $cat    = $req->category;

        $products = DB::table('products')
                    ->leftJoin("categories", "products.category_id", "=", "categories.category_id")
                    ->select("products.*", "categories.*");
        
        if(!empty($cat)){
            $products = $products->orWhere([["categories.category_id", $cat]]);
        }
        
        if(!empty($search)){
            $products = $products->orWhere([["products.product_name", "LIKE", "%".$search."%"]])
                        ->orWhere([["products.product_code", "LIKE", "%".$search."%"]]);
        }
        
        if(empty($sort)){
            $products = $products->orderBy("products.product_id", "desc")->paginate(50);
        } else if($sort == "desc"){
            $products = $products->orderBy("products.product_code", "desc")->paginate(50);
        } else {
            $products = $products->orderBy("products.product_code", "asc")->paginate(50);
        }

        foreach($products as $p){
            $totalStockIn   = DB::table('stock')->where([["product_id", $p->product_id], ["type", 1]])->sum("product_amount");
            $totalStockOut  = DB::table('stock')->where([["product_id", $p->product_id], ["type", 0]])->sum("product_amount");
            $availableStock = $totalStockIn-$totalStockOut;
            $p->product_amount = $availableStock;
        }

        return View::make("products")->with(compact("products"));
    }

    public function products_wip(Request $req){
        $sort   = $req->sort;
        $search = $req->q;
        $cat    = $req->category;

        $products = DB::table('products_wip')
                    ->leftJoin("products", "products_wip.product_id", "=", "products.product_id")
                    ->select("products_wip.*", "products.*");
        
        if(!empty($search)){
            $products = $products->orWhere([["products.product_name", "LIKE", "%".$search."%"]])
                        ->orWhere([["products.product_code", "LIKE", "%".$search."%"]]);
        }
        
        if(empty($sort)){
            $products = $products->orderBy("products_wip.product_wip_id", "desc")->paginate(50);
        } else if($sort == "desc"){
            $products = $products->orderBy("products.product_code", "desc")->paginate(50);
        } else {
            $products = $products->orderBy("products.product_code", "asc")->paginate(50);
        }

        return View::make("products_wip")->with(compact("products"));
    }

    public function product_check(Request $req){

        $product = DB::table('products')->where([["product_code", $req->pcode]])->select("product_id", "product_code","product_name")->first();
        
        $result = ["status" => 0, "data" => null];

        if(!empty($product)){
            $result = ["status" => 1, "data" => $product];
        }
        
        return response()->json($result);
    }

    public function product_save(Request $req){
        $req->validate([
            'product_code'      => 'required|unique:products,product_code,'.$req->id.',product_id|numeric',
            'product_name'      => 'required',
            'purchase_price'    => 'required|numeric',
            'sale_price'        => 'required|numeric',
            'category'          => 'required|exists:categories,category_id',
            
        ],
        [
            'product_code.required'     => 'Product Code belum diisi!',
            'product_code.numeric'      => 'Product Code harus berupa angka!',
            'product_code.unique'       => 'Product Code telah digunakan!',
            'product_name.required'     => 'Product Name belum diisi!',
            'purchase_price.required'   => 'Purchase Price belum diisi!',
            'purchase_price.numeric'    => 'Purchase Price harus berupa angka!',
            'sale_price.required'       => 'Sale Price belum diisi!',
            'sale_price.numeric'        => 'Sale Price harus berupa angka!',
            'category.required'         => 'Kategori belum dipilih!',
            'category.exists'           => 'Kategori tidak tersedia!',
        ]);

        $data = [
            "user_id"           => Auth::user()->id,
            "product_code"      => $req->product_code,
            "product_name"      => $req->product_name,
            "purchase_price"    => $req->purchase_price,
            "sale_price"        => $req->sale_price,
            "category_id"       => $req->category,
        ];

        if(empty($req->id)){
            $add = DB::table('products')->insertGetId($data);

            if($add){
                $req->session()->flash('success', "Product berhasil ditambahkan.");
            } else {
                $req->session()->flash('error', "Product gagal ditambahkan!");
            }
        } else {
            $update = DB::table('products')->where("product_id", $req->id)->update($data);

            if($update){
                $req->session()->flash('success', "Product berhasil diubah.");
            } else {
                $req->session()->flash('error', "Product gagal diubah!");
            }
        }
        
        return redirect()->back();
    }

    public function product_wip_save(Request $req){
        $req->validate([
            'product_code'      => 'required|exists:products,product_code|numeric',
            'product_amount'    => 'required|numeric',
            
        ],
        [
            'product_code.required'     => 'Product Code belum diisi!',
            'product_code.numeric'      => 'Product Code harus berupa angka!',
            'product_code.exists'       => 'Product Code tidak ditemukan!',
            'product_amount.required'   => 'Product Amount belum diisi!',
            'product_amount.numeric'    => 'Product Amount harus berupa angka!',
        ]);

        $product_id = DB::table('products')
                        ->where("product_code", $req->product_code)
                        ->select("product_id")
                        ->first()
                        ->product_id;

        $data = [
            "product_id"      => $product_id,
            "product_amount"    => $req->product_amount,
        ];

        if(empty($req->id)){
            $add = DB::table('products_wip')->insertGetId($data);

            if($add){
                $req->session()->flash('success', "Product berhasil ditambahkan.");
            } else {
                $req->session()->flash('error', "Product gagal ditambahkan!");
            }
        } else {
            $update = DB::table('products_wip')->where("product_wip_id", $req->id)->update($data);

            if($update){
                $req->session()->flash('success', "Product berhasil diubah.");
            } else {
                $req->session()->flash('error', "Product gagal diubah!");
            }
        }
        
        return redirect()->back();
    }

    public function product_delete(Request $req){
        $del = DB::table('products')->where("product_id", $req->id)->delete();

        if($del){
            $stock_id = DB::table('stock')->where("product_id", $req->id)->first();
            if(!empty($stock_id)){
                $stock_id = $stock_id->stock_id;
                DB::table('stock')->where("product_id", $req->id)->delete();
                DB::table('history')->where("stock_id", $stock_id)->delete();
            }
            $req->session()->flash('success', "Product berhasil dihapus.");
        } else {
            $req->session()->flash('error', "Product gagal dihapus!");
        }

        return redirect()->back();
    }

    public function product_wip_delete(Request $req){
        $del = DB::table('products_wip')->where([["product_wip_id", $req->id]])->delete();

        if($del){
            $req->session()->flash('success', "Product berhasil dihapus.");
        } else {
            $req->session()->flash('error', "Product gagal dihapus!");
        }

        return redirect()->back();
    }

    public function product_wip_complete(Request $req){
        $wip_id     = $req->wip_id;

        $wip        = DB::table('products_wip')->select("*")->where("product_wip_id", $wip_id)->first();
        $shelf      = DB::table('shelf')->select("shelf_id")->first()->shelf_id;
        $wipComplete = null;

        if(count(array($wip)) > 0){
            $data = new Request([
                "product_id"    => $wip->product_id,
                "amount"        => $wip->product_amount,
                "shelf"         => $shelf,
                "type"          => 1,
            ]);

            $wipComplete = $this->product_stock($data);
        }

        if($wipComplete){
            DB::table('products_wip')->where("product_wip_id", $wip_id)->delete();
            $req->session()->flash('success', "Product telah dipindahkan ke Products List.");
        } else {
            $req->session()->flash('error', "Terjadi kesalahan! Mohon coba kembali!");
        }

        return redirect()->back();
    }

    public function product_stock(Request $req){
        $product_id = $req->product_id;
        $amount     = $req->amount;
        $shelf      = $req->shelf;
        $type       = $req->type;
        
        if(!empty($amount)){
            if(!empty($req->shelf)){
                $data = [
                    "user_id"           => Auth::user()->id,
                    "product_id"        => $product_id,
                    "product_amount"    => $amount,
                    "shelf_id"          => $shelf,
                    "type"              => $type
                ];

                $totalStockIn   = DB::table('stock')->where([["product_id", $product_id], ["shelf_id", $shelf], ["type", 1]])->sum("product_amount");
                $totalStockOut  = DB::table('stock')->where([["product_id", $product_id], ["shelf_id", $shelf], ["type", 0]])->sum("product_amount");
                $availableStock = $totalStockIn-$totalStockOut;

                $endingTotalStockIn   = DB::table('stock')->where([["product_id", $product_id], ["type", 1]])->sum("product_amount");
                $endingTotalStockOut  = DB::table('stock')->where([["product_id", $product_id], ["type", 0]])->sum("product_amount");
                $endingAmount = $endingTotalStockIn-$endingTotalStockOut;

                if($type == 0){
                    if($amount > $availableStock){
                        $result = ["status" => 0, "message" => "Jumlah stock out melebihi jumlah stock yang tersedia di shelf yang dipilih!"];
                        goto resp;
                    } else {
                        $data["ending_amount"] = $endingAmount-$amount;
                    }
                } else {
                    $data["ending_amount"] = $endingAmount+$amount;
                }

                $updateStock = DB::table('stock')->insertGetId($data);

                if($updateStock){
                    $result = ["status" => 1, "message" => "Stok berhasil diupdate."];
                } else {
                    $result = ["status" => 0, "message" => "Stok gagal diupdate! Mohon coba kembali!"];
                }
            } else {
                $result = ["status" => 0, "message" => "Shelf belum dipilih!"];
            }
        } else {
            $result = ["status" => 0, "message" => "Amount belum diisi!"];
        }
        
        resp:
        return response()->json($result);
    }

    public function product_stock_history(Request $req){
        $search = $req->search;
        $history = DB::table('stock')
                    ->leftJoin("products", "stock.product_id", "=", "products.product_id")
                    ->leftJoin("shelf", "stock.shelf_id", "=", "shelf.shelf_id")
                    ->leftJoin("users", "stock.user_id", "=", "users.id")
                    ->select("stock.*", "products.product_code", "products.product_name", "shelf.shelf_name", "users.name")
                    ->orderBy("stock.stock_id", "desc");

        if(!empty($search)){
            $history = $history->where("products.product_code", "LIKE", "%".$search."%")
                        ->orWhere("products.product_name", "LIKE", "%".$search."%")
                        ->orWhere("shelf.shelf_name", "LIKE", "%".$search."%");
        }

        $history = $history->paginate(50);
        return View::make("stock_history")->with(compact("history"));
    }

    public function categories(Request $req){
        $search = $req->q;

        $categories = DB::table('categories')->select("*");

        if(!empty($search)){
            $categories = $categories->where("category_name", "LIKE", "%".$search."%");
        }

        if($req->format == "json"){
            $categories = $categories->get();

            return response()->json($categories);
        } else {
            $categories = $categories->paginate(50);

            return View::make("categories")->with(compact("categories"));
        }
    }

    public function categories_save(Request $req){
        $category_id = $req->category_id;

        $req->validate([
            'category_name'      => ['required']
            
        ],
        [
            'category_name.required'     => 'Nama Kategori belum diisi!',
        ]);

        $data = [
            "category_name"      => $req->category_name
        ];

        if(empty($category_id)){
            $add = DB::table('categories')->insertGetId($data);

            if($add){
                $req->session()->flash('success', "Kategori baru berhasil ditambahkan.");
            } else {
                $req->session()->flash('error', "Kategori baru gagal ditambahkan!");
            }
        } else {
            $edit = DB::table('categories')->where("category_id", $category_id)->update($data);

            if($edit){
                $req->session()->flash('success', "Kategori berhasil diubah.");
            } else {
                $req->session()->flash('error', "Kategori gagal diubah!");
            }
        }
        
        return redirect()->back();
    }

    public function categories_delete(Request $req){
        $del = DB::table('categories')->where("category_id", $req->delete_id)->delete();

        if($del){
            DB::table('products')->where("category_id", $req->delete_id)->update(["category_id" => null]);
            $req->session()->flash('success', "Kategori berhasil dihapus.");
        } else {
            $req->session()->flash('error', "Kategori gagal dihapus!");
        }

        return redirect()->back();
    }

    public function shelf(Request $req){
        $product_id = $req->product_id;
        $shelf = DB::table('shelf');
        if($req->format == "json"){
            if(!empty($product_id)){
                $shelf = $shelf->join("stock", "shelf.shelf_id", "stock.shelf_id")
                            ->where("stock.product_id", $product_id)->groupBy("shelf_id");
                $result = [];
                $shelf = $shelf->select("shelf.*", "stock.product_amount")->get();
                foreach($shelf as $s){
                    $totalStockIn   = DB::table('stock')->where([["product_id", $product_id], ["shelf_id", $s->shelf_id], ["type", 1]])->sum("product_amount");
                    $totalStockOut  = DB::table('stock')->where([["product_id", $product_id], ["shelf_id", $s->shelf_id], ["type", 0]])->sum("product_amount");
                    $availableStock = $totalStockIn-$totalStockOut;
                    if($availableStock > 0){
                        $s->product_amount = $availableStock;
                        $result[] = $s;
                    }
                }
            } else {
                $result = $shelf->select("shelf.*")->get();
            }
            return response()->json($result);
        } else {
            $shelf = $shelf->paginate(50);
            if(Auth::user()->role == 0){
                return View::make("shelf")->with(compact("shelf"));
            } else {
                abort(403);
            }
        }
    }

    public function shelf_save(Request $req){
        $shelf_id = $req->shelf_id;

        $req->validate([
            'shelf_name'      => ['required']
            
        ],
        [
            'shelf_name.required'     => 'Shelf Name belum diisi!',
        ]);

        $data = [
            "shelf_name"      => $req->shelf_name
        ];

        if(empty($shelf_id)){
            $add = DB::table('shelf')->insertGetId($data);

            if($add){
                $req->session()->flash('success', "Shelf baru berhasil ditambahkan.");
            } else {
                $req->session()->flash('error', "Shelf baru gagal ditambahkan!");
            }
        } else {
            $edit = DB::table('shelf')->where("shelf_id", $shelf_id)->update($data);

            if($edit){
                $req->session()->flash('success', "Shelf berhasil diubah.");
            } else {
                $req->session()->flash('error', "Shelf gagal diubah!");
            }
        }
        
        return redirect()->back();
    }

    public function shelf_delete(Request $req){
        $del = DB::table('shelf')->where("shelf_id", $req->delete_id)->delete();

        if($del){
            DB::table('stock')->where("shelf_id", $req->delete_id)->delete();
            $req->session()->flash('success', "Shelf berhasil dihapus.");
        } else {
            $req->session()->flash('error', "Shelf gagal dihapus!");
        }

        return redirect()->back();
    }

    public function generateBarcode(Request $req){
        $code       = $req->code;
        $print      = $req->print;
        $barcodeB64 = DNS1D::getBarcodePNG("".$code."", 'C128', 2, 81, array(0,0,0), true);

        if(!empty($print) && $print == true){
            return View::make("barcode_print")->with("barcode", $barcodeB64);
        } else {
            $barcode    = base64_decode($barcodeB64);
            $image      = imagecreatefromstring($barcode);
            $barcode    = imagepng($image);
            imagedestroy($image);

            return response($barcode)->header('Content-type','image/png');
        }
    }
}
