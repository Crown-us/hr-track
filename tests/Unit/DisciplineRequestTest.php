<?php

namespace Tests\Unit;

use App\Http\Requests\DisciplineRequest;
use App\Models\DisciplinaryCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DisciplineRequestTest extends TestCase
{
    public function test_edit_accepts_explicit_empty_document_from_browser(): void
    {
        $route = new Route('PUT', 'discipline/{case}', fn () => null);
        $route->bind(Request::create('/discipline/1', 'PUT'));
        $route->setParameter('case', new DisciplinaryCase);
        $request = DisciplineRequest::create('/discipline/1', 'PUT', ['document' => null]);
        $request->setRouteResolver(fn () => $route);
        $rules = $request->rules();
        $validator = Validator::make(['document' => null], ['document' => $rules['document']]);
        $this->assertTrue($validator->passes(), implode(', ', $validator->errors()->all()));
    }
}
