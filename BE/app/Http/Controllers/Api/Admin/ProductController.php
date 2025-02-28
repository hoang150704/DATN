<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\StoreProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
use App\Traits\ProductTraits;
use Dotenv\Exception\ValidationException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ProductController extends Controller
{
    use ProductTraits;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            //code...
            $products = Product::with("categories:name")->select('id', 'name', 'main_image', 'type','slug')->latest()->paginate(10);
            foreach ($products as $key => $value) {

                if ($value->main_image == null) {
                    $products[$key]['url'] = null;
                } else {
                    $url = Product::getConvertImage($value->library->url, 100, 100, 'thumb');
                    $products[$key]['url'] = $url;
                }
            }
            return response()->json($products, 200);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                "message" => "Lỗi hệ thống",
                "error" => $th->getMessage() // Trả về chi tiết lỗi
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        try {
            //code...
            DB::beginTransaction();
            $validatedData = $request->validated();
            // Xử lí thêm product
            $dataProduct = [
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'weight'=>$validatedData['weight'],
                'short_description' => $validatedData['short_description'] ?? null,
                'main_image' => $validatedData['main_image'] ?? null,
                'type' => $validatedData['type'],
            ];
            //Xử lí slug
            $slug = $this->handleSlug($dataProduct['name'],'create');
            $dataProduct['slug'] = $slug;

            //Thêm sản phẩm
            $product = Product::create($dataProduct);

            // Thêm list ảnh
            $images = $validatedData['images'] ?? [];
            $this->addImages($images,$product->id);

            // Xử lí danh mục
            $categories = $validatedData['categories'] ?? [];
            $this->addCategories($categories,$product->id);

            // Xử lí thêm sản phẩm biến thể hay đơn giản 
            if ($request->type == 1) {
                $this->createBasicProduct($validatedData['variants'], $product->id);
            } else {
                $this->createVariantProduct($validatedData['variants'], $validatedData['attributes'], $product->id);
            }
            // Hoàn thành
            DB::commit();
            return response()->json(['message' => 'Bạn đã thêm sản phẩm thành công'], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
            return response()->json([
                "message" => "Lỗi hệ thống",
                "error" => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            //code...
            $product = Product::select('id', 'name', 'weight','description', 'short_description', 'main_image', 'slug', 'type')->findOrFail($id);
            //Covert dữ liệu
            $convertData = [
                "id" => $product->id,
                "name" => $product->name,
                "weight" => $product->weight,
                "description" => $product->description,
                "short_description" => $product->short_description,
                "main_image" => $product->main_image,
                "url_main_image" => $product->main_image == null ? "" : Product::getConvertImage($product->library->url, 400, 400, 'thumb'),
                "type" => $product->type,
                "slug" => $product->slug,
            ];
            //List biến thể
            $convertData['variants'] = $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'regular_price' => $variant->regular_price,
                    'sale_price' => $variant->sale_price,
                    'stock_quantity' => $variant->stock_quantity,
                    'values' => $variant->values->map(function ($value) {
                        return [
                            'name' => $value->attributeValue->name
                        ];
                    })
                ];
            });
            //Categories
            $convertData['categories'] = $product->categories->pluck('id')->toArray();
            //Thư viện ảnh
            $convertData['product_images'] = $product->productImages->map(function ($image) {
                return [
                    'public_id' => $image->id,
                    'url' => Product::getConvertImage($image->url, 400, 400, 'thumb')
                ];
            });

            return response()->json($convertData, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                "message" => "Lỗi hệ thống",
                "error" => $th->getMessage() // Trả về chi tiết lỗi
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, string $id)
    {
        //
        try {
            //code...
            // DB::beginTransaction();
            $product = Product::findorFail($id);
            //Sửa sản phẩm
            $validatedData = $request->validated();

            //COnvert data
            $dataProduct = [
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'short_description' => $validatedData['short_description'] ?? null,
                'weight'=>$validatedData['weight'],
                'main_image' => $validatedData['main_image'] ?? null,
                'type' => $validatedData['type'],
                'slug' => $validatedData['slug']
            ];
            $dataProduct['slug'] = $this->handleSlug($dataProduct['slug'],'update',$id);

            //Tiến hành sửa biến thể or basic
            if ($product->type == 1) {  //Nếu ban dầu là sp đơn giản
                if ($dataProduct['type'] == 1) {  // Sau khi update vẫn là sp đơn giản
                    //Update sản phẩm đơn giản
                    $this->updateBasicProduct($validatedData['variants'],$id);

                } else { // Sau khi update là sp biến thể
                    //Ẩn biến thể cũ đi
                    $this->deletProductVaration($product);

                    // Thêm biến thể
                    $this->createVariantProduct($validatedData['variants'],$validatedData['attributes'],$id);

                }
            } else { // Trước đó là sp biến thể 
                if ($dataProduct['type'] == 1) { // sau update là sp đơn giản
                    //Ẩn biến thể cũ
                    $this->deletProductVaration($product);

                    //Thêm sản phẩm đơn giản
                    $this->createBasicProduct($validatedData['variants'],$id);
                    
                } else { //sau update vẫn là biến thể
                    //Update sản phẩm biến thể
                    $this->updateVariantProduct($validatedData['variants'],$id);

                }
            }
            $product->update($dataProduct); 
            $product->categories()->sync($validatedData['categories']);
            $product->productImages()->sync($validatedData['images']);
            return response()->json(['Bạn đã sửa thành công'], 200);
        } catch (\Exception $e) {
            //throw $th;
            // DB::rollBack();
            Log::error($e);
            return response()->json([
                "message" => "Lỗi hệ thống",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);

            if ($product->trashed()) {
                return response()->json(['message' => 'Sản phẩm đã được xóa mềm'], 400);
            }
            $product->delete();

            return response()->json(['message' => 'Sản phẩm đã được chuyển vào thùng rác'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Sản phẩm không tồn tại'], 404);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $th->getMessage()

            ], 500);
        }
    }

    public function listProductForOrder()
    {
        try {
            $products = Product::with([
                'variants' => function ($query) {
                    $query->select('id', 'product_id', 'stock_quantity', 'regular_price', 'sale_price');
                },
                'variants.values.attributeValue' => function ($query) {
                    $query->select('id', 'name');
                }
            ])->select('id', 'name', 'main_image', 'weight', 'type')->get();
    
            // Kiểm tra nếu không có sản phẩm nào
            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Không có sản phẩm nào!',
                    'data' => []
                ], 200);
            }
            
            // Format lại dữ liệu để chỉ lấy mảng tên thuộc tính
            $products->transform(function ($product) {
                $product->variants->transform(function ($variant) {
                    $variant->values = $variant->values ? $variant->values->pluck('attributeValue.name')->toArray() : [];
                    return $variant;
                });
                return $product;
            });
    
            return response()->json([
                'status' => 'success',
                'message' => 'Lấy danh sách sản phẩm thành công!',
                'data' => $products
            ], 200);
    
        } catch (Exception $e) {
            // Ghi log lỗi
            Log::error('Lỗi khi lấy danh sách sản phẩm: ' . $e->getMessage());
    
            return response()->json([
                'status' => 'error',
                'message' => 'Đã xảy ra lỗi khi lấy danh sách sản phẩm!',
                'error' => $e->getMessage()
            ], 500);
        }}
}
