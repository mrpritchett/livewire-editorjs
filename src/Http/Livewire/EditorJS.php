<?php

namespace Maxeckel\LivewireEditorjs\Http\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use App\Models\Upload;
use Illuminate\Support\Str;

class EditorJS extends Component
{
    use WithFileUploads;

    public $uploads = [];

    public $editorId;

    public $data;

    public $class;

    public $style;

    public $readOnly;

    public $placeholder;

    public $uploadDisk;

    public $downloadDisk;

    public $logLevel;

    public function mount(
        $editorId,
        $value = [],
        $class = '',
        $style = '',
        $readOnly = false,
        $placeholder = null,
        $uploadDisk = null,
        $downloadDisk = null
    ) {
        if (is_null($uploadDisk)) {
            $uploadDisk = config('livewire-editorjs.default_img_upload_disk');
        }

        if (is_null($downloadDisk)) {
            $downloadDisk = config('livewire-editorjs.default_img_download_disk');
        }

        if (is_null($placeholder)) {
            $placeholder = config('livewire-editorjs.default_placeholder');
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $this->editorId = $editorId;
        $this->data = $value;
        $this->class = $class;
        $this->style = $style;
        $this->readOnly = $readOnly;
        $this->placeholder = $placeholder;
        $this->uploadDisk = $uploadDisk;
        $this->downloadDisk = $downloadDisk;

        $this->logLevel = config('livewire-editorjs.editorjs_log_level');
    }

    public function completedImageUpload(string $uploadedFileName, string $eventName, $fileName = null)
    {
        if (userIsCurrentTenant(Auth::user())) {
            $uuid = (string) Str::uuid();

            // Upload image
            $file = collect($this->uploads)
                ->filter(function (TemporaryUploadedFile $item) use ($uploadedFileName) {
                    return $item->getFilename() === $uploadedFileName;
                })
                ->first();
            $path = $file->storeAs(
                'uploads',
                $uuid . '.' . $file->extension(),
                'local'
            );

            // Create database entry
            $upload = new Upload;

            $upload->tenant_id = tenant('id');
            $upload->name      = $uuid;
            $upload->url       = $path;

            $upload->save();

            $this->dispatchBrowserEvent($eventName, [
                'url' => config('app.url') . '/storage/tenant' . tenant('id') . '/' . $upload->url,
            ]);
        }
    }

    public function loadImageFromUrl(string $url)
    {
        $name = basename($url);
        $content = file_get_contents($url);

        Storage::disk($this->downloadDisk)->put($name, $content);

        return Storage::disk($this->downloadDisk)->url($name);
    }

    public function save()
    {
        $this->emit("editorjs-save:{$this->editorId}", $this->data);
    }

    public function render()
    {
        return view('livewire-editorjs::livewire.editorjs');
    }
}
