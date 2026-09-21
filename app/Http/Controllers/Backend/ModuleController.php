<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ModuleController extends Controller
{
    public function index() {
        $modules = DB::table('installed_modules')
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderByDesc('id')
            ->get();
        return view('backend.modules.index', compact('modules'));
    }

    public function create() {
        return view('backend.modules.create');
    }

    public function store(Request $request) {
        $request->validate(['module_zip' => 'required|file|mimes:zip|max:51200']);
        $file = $request->file('module_zip');
        $path = $file->store('modules', 'public');
        
        // Try to read module.json from ZIP
        $zip = new \ZipArchive();
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $description = '';
        $version = '1.0.0';
        $author = '';
        
        if ($zip->open($file->getRealPath()) === true) {
            $jsonContent = $zip->getFromName('module.json');
            if ($jsonContent) {
                $meta = json_decode($jsonContent, true);
                $name = $meta['name'] ?? $name;
                $description = $meta['description'] ?? '';
                $version = $meta['version'] ?? '1.0.0';
                $author = $meta['author'] ?? '';
            }
            $zip->close();
        }
        
        DB::table('installed_modules')->insert([
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'author' => $author,
            'zip_path' => $path,
            'is_enabled' => 1,
            'company_id' => session('company.company_id'),
            'del_status' => 'Live',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return redirect()->route('modules.index')->with('success', 'Module installed successfully!');
    }

    public function toggle(Request $request, $id) {
        $module = DB::table('installed_modules')->where('id', $id)->first();
        if ($module) {
            DB::table('installed_modules')->where('id', $id)->update([
                'is_enabled' => !$module->is_enabled,
                'updated_at' => now(),
            ]);
        }
        return response()->json(['success' => true, 'is_enabled' => !$module->is_enabled]);
    }

    public function destroy($id) {
        DB::table('installed_modules')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        return redirect()->route('modules.index')->with('success', 'Module removed.');
    }
}
