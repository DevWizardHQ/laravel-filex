---
name: filex
description: Async file uploads with Dropzone.js, temp-first pattern, chunked uploads, security scanning, and validation rules.
---

# Filex — Async File Upload System

## When to use this skill

Activate this skill when:
- Adding file upload to any form (images, documents, media, attachments)
- Creating or editing models that have file/image columns
- Validating uploaded files
- Moving files between storage disks
- Building UI with drag-and-drop upload, progress bars, or file previews

## Core Concept — Mandatory Temp-First Pattern

Filex uses a temp-first upload pattern. This is NOT optional — it is how the package works:

1. User drops/selects files in the Blade component
2. Files upload asynchronously via AJAX to `temp/` on the local disk
3. The form submits temp path strings (e.g., `temp/abc123_photo.jpg`), NOT file objects
4. Your controller receives string paths and moves them to permanent storage
5. The controller stores the final permanent path in the database

**This means**: form fields for Filex uploads contain **strings**, not `UploadedFile` objects. All validation and controller logic MUST treat them as strings starting with `temp/`.

## Strict Rules

### Blade / Frontend Rules

1. **ALWAYS include `@filexAssets` once per page** before any `<x-filex-uploader>` component:
   ```blade
   @filexAssets

   <form method="POST" action="{{ route('posts.store') }}">
       @csrf
       <x-filex-uploader name="avatar" />
       <button type="submit">Submit</button>
   </form>
   ```

2. **ALWAYS set the `name` prop** to match the field name your controller expects.

3. **ALWAYS set `mimes` prop** to restrict file types. Never leave it open:
   ```blade
   {{-- CORRECT — restricted to specific types --}}
   <x-filex-uploader name="photo" mimes="jpeg,png,webp" :max-size="5" />

   {{-- WRONG — accepts any file type --}}
   <x-filex-uploader name="photo" />
   ```

4. **ALWAYS set `:max-size`** to limit file size in MB.

5. **For multiple files, ALWAYS set `:multiple="true"` and `:max-files`**:
   ```blade
   <x-filex-uploader
       name="documents"
       :multiple="true"
       :max-files="10"
       :max-size="10"
       mimes="pdf,doc,docx"
   />
   ```

6. **For edit forms, ALWAYS pass existing files via `value` prop**:
   ```blade
   <x-filex-uploader
       name="avatar"
       mimes="jpeg,png,webp"
       :max-size="5"
       :value="$user->avatar"
   />

   {{-- Multiple files --}}
   <x-filex-uploader
       name="documents"
       :multiple="true"
       :value="$post->documents"
   />
   ```

7. **NEVER add `enctype="multipart/form-data"`** to the form tag. Filex handles uploads via AJAX before form submission. The form only submits string paths. However, including it causes no harm.

8. **For image uploads, use dimension constraints** when the design requires specific sizes:
   ```blade
   <x-filex-uploader
       name="banner"
       mimes="jpeg,png"
       :max-size="5"
       dimensions="min_width=1200,min_height=630"
   />
   ```

### Controller Rules

9. **ALWAYS use the `HasFilex` trait** in controllers that handle file uploads:
    ```php
    use DevWizard\Filex\Traits\HasFilex;

    class PostController extends Controller
    {
        use HasFilex;
    }
    ```

10. **ALWAYS validate temp paths** before moving. Use either basic validation or `ValidFileUpload` rules:

    ```php
    // Option A: Basic validation (minimum)
    $request->validate([
        'avatar' => ['required', 'string', 'starts_with:temp/'],
    ]);

    // Option B: Full security validation (recommended for production)
    use DevWizard\Filex\Rules\ValidFileUpload;

    $request->validate([
        'avatar' => ['required', ValidFileUpload::forImages(maxSizeMB: 5)],
        'document' => ['required', ValidFileUpload::forDocuments(maxSizeMB: 10)],
    ]);
    ```

11. **For multiple file fields, ALWAYS validate both the array and each item**:
    ```php
    $request->validate([
        'documents' => ['required', 'array', 'max:10'],
        'documents.*' => ['string', 'starts_with:temp/'],
    ]);
    ```

12. **Use the trait's `moveFile()` / `moveFiles()` methods** — NEVER manually copy or move temp files:
    ```php
    // Single file — returns ?string (path or null)
    $path = $this->moveFile($request, 'avatar', 'avatars');

    // Multiple files — returns array of paths
    $paths = $this->moveFiles($request, 'documents', 'post-documents');
    ```

13. **ALWAYS specify visibility explicitly** for security-sensitive files:
    ```php
    // Public files (accessible via URL)
    $path = $this->moveFilePublic($request, 'avatar', 'avatars');

    // Private files (no public URL, requires signed URL or streaming)
    $path = $this->moveFilePrivate($request, 'contract', 'contracts');
    ```

14. **To use a specific storage disk (like S3)**, pass it as the 4th argument:
    ```php
    $path = $this->moveFile($request, 'avatar', 'avatars', 's3');
    $paths = $this->moveFiles($request, 'documents', 'docs', 's3');
    ```

15. **Store the returned path in the model**, not the temp path:
    ```php
    // CORRECT
    $avatarPath = $this->moveFile($request, 'avatar', 'avatars');
    $post = Post::create([
        'title' => $request->title,
        'avatar' => $avatarPath,  // Stores: "avatars/abc123_photo.jpg"
    ]);

    // WRONG — stores temp path which expires
    $post = Post::create([
        'avatar' => $request->avatar,  // Stores: "temp/abc123_photo.jpg"
    ]);
    ```

### Facade Rules

16. **Use the `Filex` facade for file operations outside controllers** (services, jobs, commands):
    ```php
    use DevWizard\Filex\Facades\Filex;

    // Single file
    $result = Filex::moveFile($tempPath, 'avatars');
    if ($result->isSuccess()) {
        $permanentPath = $result->getPath();
    }

    // Multiple files
    $result = Filex::moveFiles($tempPaths, 'documents', 's3', 'private');
    $successfulPaths = $result->getPaths();
    $failedItems = $result->getFailed();
    ```

17. **ALWAYS check `FilexResult` for errors** when using the facade directly:
    ```php
    $result = Filex::moveFiles($tempPaths, 'uploads');

    if (!$result->isAllSuccess()) {
        $errors = $result->getErrorMessages();
        // Handle failed uploads
        Log::warning('Upload failures', ['errors' => $errors]);
    }

    $paths = $result->getPaths(); // Only successful paths
    ```

## Complete Implementation Patterns

### Pattern: Create Form with Single Image + Multiple Documents

**Blade view:**

```blade
@filexAssets

<form method="POST" action="{{ route('posts.store') }}">
    @csrf

    <div>
        <x-filex-uploader
            name="featured_image"
            label="Featured Image"
            :required="true"
            :max-size="5"
            mimes="jpeg,png,webp"
            help-text="Recommended: 1200x630px"
        />
        @error('featured_image') <span class="text-red-500">{{ $message }}</span> @enderror
    </div>

    <div>
        <x-filex-uploader
            name="attachments"
            label="Attachments"
            :multiple="true"
            :max-files="5"
            :max-size="10"
            mimes="pdf,doc,docx,xlsx,zip"
        />
        @error('attachments') <span class="text-red-500">{{ $message }}</span> @enderror
        @error('attachments.*') <span class="text-red-500">{{ $message }}</span> @enderror
    </div>

    <button type="submit">Create Post</button>
</form>
```

**Controller:**

```php
use DevWizard\Filex\Traits\HasFilex;
use DevWizard\Filex\Rules\ValidFileUpload;

class PostController extends Controller
{
    use HasFilex;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'featured_image' => ['required', ValidFileUpload::forImages(5)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [ValidFileUpload::forDocuments(10)],
        ]);

        $imagePath = $this->moveFilePublic($request, 'featured_image', 'posts/images');
        $attachmentPaths = $this->moveFilesPrivate($request, 'attachments', 'posts/attachments');

        $post = Post::create([
            'title' => $validated['title'],
            'featured_image' => $imagePath,
            'attachments' => $attachmentPaths,
        ]);

        return redirect()->route('posts.show', $post);
    }
}
```

### Pattern: Edit Form with Existing Files

**Controller:**

```php
public function edit(Post $post)
{
    return view('posts.edit', compact('post'));
}

public function update(Request $request, Post $post)
{
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'featured_image' => ['nullable', ValidFileUpload::forImages(5)],
        'attachments' => ['nullable', 'array', 'max:5'],
        'attachments.*' => [ValidFileUpload::forDocuments(10)],
    ]);

    $data = ['title' => $validated['title']];

    // Only move file if a new one was uploaded (field contains a temp/ path)
    if ($request->filled('featured_image') && str_starts_with($request->featured_image, 'temp/')) {
        $data['featured_image'] = $this->moveFilePublic($request, 'featured_image', 'posts/images');
    }

    if ($request->filled('attachments')) {
        $data['attachments'] = $this->moveFilesPrivate($request, 'attachments', 'posts/attachments');
    }

    $post->update($data);

    return redirect()->route('posts.show', $post);
}
```

**Blade view (edit):**

```blade
<x-filex-uploader
    name="featured_image"
    label="Featured Image"
    :max-size="5"
    mimes="jpeg,png,webp"
    :value="$post->featured_image"
/>

<x-filex-uploader
    name="attachments"
    :multiple="true"
    :max-files="5"
    :max-size="10"
    mimes="pdf,doc,docx"
    :value="$post->attachments"
/>
```

## Validation Reference

### Preset rules (recommended — use these first)

| Method | Allowed Types | Default Max |
|--------|--------------|-------------|
| `ValidFileUpload::forImages(5)` | jpeg, png, gif, webp, svg, bmp, ico | 5 MB |
| `ValidFileUpload::forDocuments(10)` | pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv | 10 MB |
| `FileRule::forArchives(50)` | zip, rar, 7z, tar, gz | 50 MB |
| `FileRule::forAudio(20)` | mp3, wav, ogg, flac, aac | 20 MB |
| `FileRule::forVideo(100)` | mp4, avi, mkv, mov, wmv, webm | 100 MB |
| `FileRule::forType('csv', 'text/csv', 10)` | Custom single type | Custom |
| `FileRule::custom([exts], [mimes], maxMB)` | Custom multiple types | Custom |

### Granular rules (combine as needed)

```php
use DevWizard\Filex\Support\FilexRule;

$request->validate([
    'file' => [
        FilexRule::file(),                              // File exists and readable
        FilexRule::mimes('jpeg,png,pdf'),                // Allowed extensions
        FilexRule::max(10485760),                         // Max bytes
        FilexRule::image(),                              // Must be valid image
        FilexRule::dimensions('min_width=100,ratio=16/9'), // Dimension constraints
    ],
]);
```

### String-based rules (for dynamic/config-driven validation)

```php
$request->validate([
    'file' => ['filex_file', 'filex_mimes:jpeg,png', 'filex_max:10485760', 'filex_image'],
]);
```

## Configuration Reference (`config/filex.php`)

```php
'storage' => [
    'disks' => ['default' => 'public', 'temp' => 'local'],
    'max_file_size' => 10,           // MB — global default
    'temp_expiry_hours' => 24,       // Hours before temp files are cleaned
    'visibility' => ['default' => 'public'],
],
'upload' => [
    'chunk' => ['size' => 1048576, 'max_retries' => 3, 'timeout' => 30000],
],
'routes' => [
    'prefix' => 'filex',             // URL prefix for upload routes
    'middleware' => [],               // Additional middleware for routes
],
'security' => [
    'suspicious_detection' => ['enabled' => true, 'quarantine_enabled' => true],
],
```

## Artisan Commands

```bash
php artisan filex:install               # Publish config and assets
php artisan filex:cleanup-temp          # Clean expired temp files
php artisan filex:cleanup-temp --dry-run # Preview cleanup
php artisan filex:optimize              # Performance tools
php artisan filex:info                  # Package info
```

## Common Anti-Patterns to Avoid

| Anti-Pattern | Correct Pattern |
|---|---|
| Storing `temp/` paths in database | ALWAYS `moveFile()` first, store the returned permanent path |
| Using `$request->file('avatar')` | Filex fields are strings, use `$request->avatar` or `moveFile($request, 'avatar', ...)` |
| Using Laravel's `store()` / `storeAs()` on Filex fields | Use `$this->moveFile()` or `Filex::moveFile()` |
| Skipping validation on temp paths | ALWAYS validate with `starts_with:temp/` or `ValidFileUpload` |
| Omitting `@filexAssets` in layout | Component JS/CSS won't load, uploads will silently fail |
| No `mimes` restriction on uploader | ALWAYS set `mimes` to prevent unrestricted file types |
| Manual `Storage::move()` on temp files | Use `HasFilex::moveFile()` — it handles metadata, security, and atomic writes |
| Using `required` validation for edit forms | Use `nullable` for file fields on update — value is only present when changed |
