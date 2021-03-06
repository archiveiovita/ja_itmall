<?php

namespace Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValueTranslation;
use App\Models\ProductCategory;
use App\Models\ParameterCategory;
use App\Models\SubProductParameter;
use App\Models\Product;
use App\Models\SubProduct;
use App\Models\ParameterGroup;


class ParametersController extends Controller
{
    public function index()
    {
        $parameters = Parameter::orderBy('id', 'asc')->get();

        return view('admin::admin.parameters.index', compact('parameters'));
    }

    public function create()
    {
        $categories = ProductCategory::with(['children.translation', 'translation'])->where('parent_id', 0)->get();
        $groups = ParameterGroup::get();

        return view('admin::admin.parameters.create', compact('categories', 'groups'));
    }

    public function store(Request $request)
    {
        $names = array_filter($request->get('name'), function($var){return !is_null($var);} );
        $units = array_filter($request->get('unit'), function($var){return !is_null($var);} );
        $languages = array_filter($request->get('langs'), function($var){return !is_null($var);} );

        $parameter = Parameter::create([
            'type' => $request->get('type'),
            'key' => $request->get('key'),
            'in_filter' => $request->get('filter') ? 1 : 0,
            'multilingual' => $request->get('multilingual') ? 1 : 0,
            'multilingual_title' => $request->get('multilingualTitle') ? 1 : 0,
            'multilingual_unit' => $request->get('multilingualUnit') ? 1 : 0,
            'main' => $request->get('main') ? 1 : 0,
            'group_id' => $request->get('groupId'),
        ]);

        foreach ($languages as $key => $language) {
            $parameter->translation()->create([
                'lang_id' => $language['id'],
                'name' => $request->get('multilingualTitle') ? @$names[$language['id']] : @$names[$this->lang->id],
                'unit' => $request->get('multilingualUnit') ? @$units[$language['id']] : @$units[$this->lang->id],
            ]);
        }

        // if ($request->get('multilingual')) {
        //     foreach ($names as $key => $name) {
        //         $parameter->translation()->create([
        //             'lang_id' => $key,
        //             'name' => $name,
        //             'unit' => @$units[$key]
        //         ]);
        //     }
        // }else{
        //     $parameter->translation()->create([
        //         'lang_id' => $this->lang->id,
        //         'name' => @$names[$this->lang->id],
        //         'unit' => @$units[$this->lang->id],
        //     ]);
        // }

        $this->saveNewPrameterValues($request, $parameter);
        $this->attributeParameterTocategory($request, $parameter);

        return 'true';
    }

    private function generateSubproducts($request, $parameter)
    {
        $childCategories = SubProductParameter::where('parameter_id', $parameter->id)->pluck('category_id')->toArray();

        $parameterId = $parameter->id;

        if (count($childCategories) > 0) {
            foreach ($childCategories as $key => $category) {
                $parameterSubproduct = SubProductParameter::where('category_id', $category)->first();

                if (is_null($parameterSubproduct)) {
                    SubProductParameter::create([
                        'category_id' => $category,
                        'parameter_id' => $parameterId,
                    ]);
                }else{
                    SubProductParameter::where('id', $parameterSubproduct->id)->update([
                        'category_id' => $category,
                        'parameter_id' => $parameterId,
                    ]);
                }
            }
        }

        $parameter = Parameter::find($parameterId);
        $products = Product::whereIn('category_id', $childCategories)->get();
        $x = 'A';

        if (!is_null($parameter)) {
            if (count($parameter->parameterValues()->get())) {
                foreach ($products as $key => $product) {
                    $x = 'A';
                    foreach ($parameter->parameterValues()->get() as $key => $value) {
                        $subproduct = SubProduct::where('product_id', $product->id)->where('parameter_id', $parameterId)->where('value_id', $value->id)->first();
                        $combinationJSON = [ $parameterId => $value->id ];
                        if (is_null($subproduct)) {
                            SubProduct::create([
                                'product_id' => $product->id,
                                'parameter_id' => $parameterId,
                                'value_id' => $value->id,
                                'code' => $product->code.$x,
                                'combination' => json_encode($combinationJSON),
                                'price' => $product->price,
                                'actual_price' => $product->actual_price,
                                'discount' =>  $product->discount,
                                'stoc' =>  $product->stock,
                                'active' =>  1,
                            ]);
                        }else{
                            SubProduct::where('id', $subproduct->id)->update([
                                'product_id' => $product->id,
                                'parameter_id' => $parameterId,
                                'value_id' => $value->id,
                                'code' => $product->code.$x,
                                'combination' => json_encode($combinationJSON),
                            ]);
                        }
                        $x++;
                    }
                }
            }
        }

        $parameterValuesId = $parameter->parameterValues()->get()->pluck('id')->toArray();
        SubProduct::whereIn('product_id', $products->pluck('id')->toArray())->whereNotIn('value_id', $parameterValuesId)->delete();
        SubProduct::whereIn('product_id', $products->pluck('id')->toArray())->where('parameter_id', '!=', $parameterId)->delete();

    }

    private function attributeParameterTocategory($request, $parameter)
    {
        $categoriesId = array_filter($request->get('categories'), function($var){return !is_null($var);} );
        $categoriesId = array_filter($categoriesId, function($var){return $var !== false;} );

        if (count($categoriesId) > 0) {
            $parameter->categories()->delete();
            foreach ($categoriesId as $key => $categoryId) {
                if ($categoryId !== false) {
                    $parameter->categories()->create([
                        'category_id' => $key,
                    ]);
                }
            }
        }
    }

    private function saveNewPrameterValues($request, $parameter)
    {
        $paramValues = [];
        if (count($request->get('paramValues'))) {
            foreach ($request->get('paramValues') as $key => $value) {
                $paramValues[] = array_filter($value, function($var){return !is_null($var);});
            }
        }

        $paramValues = array_filter($paramValues);

        if (count($paramValues) > 0) {
            foreach ($paramValues as $key => $values) {
                $parameterValue = $parameter->parameterValues()->create();

                if ($request->get('multilingual')) {
                    foreach ($values as $key => $value) {
                        $parameterValue->translation()->create([
                            'lang_id' => $key,
                            'name' => $value
                        ]);
                    }
                }else{
                    $parameterValue->translation()->create([
                        'lang_id' => $this->lang->id,
                        'name' => @$values[$this->lang->id]
                    ]);
                }
            }
        }
    }

    private function updateOldPrameterValues($request, $parameter)
    {
        $paramValues = [];
        if (count($request->get('oldParamValues'))) {
            foreach ($request->get('oldParamValues') as $key => $value) {
                 if (is_array($value)) {
                     $paramValues[$key] = array_filter($value, function($var){return !is_null($var);});
                 }
            }
        }

        $paramValues = array_filter($paramValues);

        if (count($paramValues) > 0) {
            foreach ($paramValues as $key => $values) {
                if ($request->get('multilingual')) {
                    foreach ($values as $lang_key => $value) {
                        $valueTranslation = ParameterValueTranslation::where('lang_id', $lang_key)->where('parameter_value_id', $key)->first();
                        if (!is_null($valueTranslation)) {
                            ParameterValueTranslation::where('lang_id', $lang_key)
                                            ->where('parameter_value_id', $key)
                                            ->update(['name' => $value]);
                        }else{
                            ParameterValueTranslation::create([
                                                'lang_id' => $lang_key,
                                                'parameter_value_id' => $key,
                                                'name' => $value
                                            ]);
                        }
                    }
                }else{
                    $valueTranslation = ParameterValueTranslation::where('lang_id', $this->lang->id)->where('parameter_value_id', $key)->first();

                    if (!is_null($valueTranslation)) {
                        ParameterValueTranslation::where('lang_id', $this->lang->id)
                                        ->where('parameter_value_id', $key)
                                        ->update(['name' => @$values[$this->lang->id]]);
                    }else{
                        ParameterValueTranslation::create([
                                            'lang_id' => $this->lang->id,
                                            'parameter_value_id' => $key,
                                            'name' => @$values[$this->lang->id]]);
                    }
                    ParameterValueTranslation::where('lang_id', $this->lang->id)
                                    ->where('parameter_value_id', $key)
                                    ->update(['name' => @$values[$this->lang->id]]);

                    ParameterValueTranslation::where('lang_id', '!=', $this->lang->id)
                                    ->where('parameter_value_id', $key)
                                    ->delete();
                }
            }
        }
    }

    public function edit($id)
    {
        $categories = ProductCategory::with(['children.translation', 'children.children.translation', 'children.children.children.translation', 'translation'])->where('parent_id', 0)->get();

        $parameter = Parameter::with(['translations', 'parameterValues.translations', 'categories'])->findOrFail($id);

        $groups = ParameterGroup::get();

        return view('admin::admin.parameters.edit', compact('parameter', 'categories', 'groups'));
    }

    public function update(Request $request, $id)
    {
        $parameter = Parameter::findOrFail($id);
        $names = array_filter($request->get('name'), function($var){return !is_null($var);} );
        $units = array_filter($request->get('unit'), function($var){return !is_null($var);} );
        $languages = array_filter($request->get('langs'), function($var){return !is_null($var);} );

        $parameter->update([
            'type' => $request->get('type'),
            'key' => $request->get('key'),
            'in_filter' => $request->get('filter') ? 1 : 0,
            'multilingual' => $request->get('multilingual') ? 1 : 0,
            'multilingual_title' => $request->get('multilingualTitle') ? 1 : 0,
            'multilingual_unit' => $request->get('multilingualUnit') ? 1 : 0,
            'main' => $request->get('main') ? 1 : 0,
            'group_id' => $request->get('groupId'),
        ]);

        $parameter->translations()->delete();
        foreach ($languages as $key => $language) {
            $parameter->translation()->create([
                'lang_id' => $language['id'],
                'name' => $request->get('multilingualTitle') ? @$names[$language['id']] : @$names[$this->lang->id],
                'unit' => $request->get('multilingualUnit') ? @$units[$language['id']] : @$units[$this->lang->id],
            ]);
        }

        // if ($request->get('multilingual')) {
        //     $parameter->translations()->delete();
        //     foreach ($names as $key => $name) {
        //         $parameter->translation()->create([
        //             'lang_id' => $key,
        //             'name' => $name,
        //             'unit' => @$units[$key]
        //         ]);
        //     }
        // }else{
        //     $parameter->translations()->delete();
        //     $parameter->translation()->create([
        //         'lang_id' => $this->lang->id,
        //         'name' => @$names[$this->lang->id],
        //         'unit' => @$units[$this->lang->id],
        //     ]);
        // }

        $this->updateOldPrameterValues($request, $parameter);

        $this->saveNewPrameterValues($request, $parameter);

        $this->attributeParameterTocategory($request, $parameter);

        $this->generateSubproducts($request, $parameter);

        return 'true';
    }

    public function destroy($id)
    {
        $parameter = Parameter::findOrFail($id);
        $parameter->delete();

        session()->flash('message', 'Item has been deleted!');

        return redirect()->route('parameters.index');
    }

    public function removeOldValue(Request $request)
    {
        $id = $request->get('id');
        $parameterId = $request->get('parameterId');

        $parameterValue = ParameterValue::where('id', $id)->first();

        $parameterValue->delete();

        $parameter = Parameter::with(['translations', 'parameterValues.translations'])->findOrFail($parameterId);
        return $parameter;
    }

}
