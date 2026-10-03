<div class="card mb-3" id="property-photos" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">صور العقار</h3>
            <div class="text-muted small mt-1">{{ $property->photos->count() }} صورة مرتبطة بهذا العقار.</div>
        </div>
    </div>

    <div class="card-body">
        @if($property->photos->isNotEmpty())
            <div class="row g-2 mb-3">
                @foreach($property->photos as $photo)
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="position-relative border rounded overflow-hidden">
                            <a href="{{ $photo->url }}" target="_blank" class="d-block">
                                <img src="{{ $photo->url }}"
                                     alt="{{ $photo->caption ?? $photo->original_name }}"
                                     class="w-100"
                                     style="height:160px;object-fit:cover;">
                            </a>

                            @can('update', $property)
                                <form method="POST"
                                      action="{{ route('properties.photos.delete', [$property, $photo]) }}"
                                      class="position-absolute"
                                      style="top:6px;left:6px;"
                                      onsubmit="return confirm('حذف هذه الصورة؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">حذف</button>
                                </form>
                            @endcan

                            @if($photo->caption)
                                <div class="position-absolute bottom-0 start-0 end-0 px-2 py-1 text-white"
                                     style="background:rgba(0,0,0,.6);font-size:12px;">
                                    {{ $photo->caption }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-muted mb-3">لا توجد صور للعقار حتى الآن.</div>
        @endif

        @can('update', $property)
            <form method="POST"
                  action="{{ route('properties.photos.upload', $property) }}"
                  enctype="multipart/form-data"
                  id="property-photo-upload-form">
                @csrf

                <div class="border border-2 border-dashed rounded p-3 text-center"
                     id="property-photo-drop-zone"
                     style="cursor:pointer;border-color:#c5d2de !important;">
                    <div class="fw-bold mb-1">اسحب صور العقار هنا أو اضغط للاختيار</div>
                    <div class="text-muted small">JPG / PNG / WebP، حتى 10 صور في المرة الواحدة، بحد أقصى 10MB للصورة.</div>
                    <input type="file"
                           name="photos[]"
                           id="property-photo-input"
                           class="d-none"
                           multiple
                           accept="image/jpeg,image/png,image/webp">
                </div>

                @error('photos') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                @error('photos.*') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                <div id="property-photo-preview" class="row g-2 mt-2" style="display:none;"></div>

                <div class="d-flex justify-content-between align-items-center mt-3" id="property-photo-actions" style="display:none;">
                    <span class="text-muted small" id="property-photo-count"></span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="property-photo-clear">مسح</button>
                        <button type="submit" class="btn btn-primary btn-sm">رفع الصور</button>
                    </div>
                </div>
            </form>
        @endcan
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var zone = document.getElementById('property-photo-drop-zone');
    var input = document.getElementById('property-photo-input');
    var preview = document.getElementById('property-photo-preview');
    var actions = document.getElementById('property-photo-actions');
    var count = document.getElementById('property-photo-count');
    var clear = document.getElementById('property-photo-clear');

    if (!zone || !input) return;

    zone.addEventListener('click', function () {
        input.click();
    });

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.style.background = '#f1f5f9';
    });

    zone.addEventListener('dragleave', function () {
        zone.style.background = '';
    });

    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.style.background = '';
        input.files = e.dataTransfer.files;
        render();
    });

    input.addEventListener('change', render);

    function render() {
        preview.innerHTML = '';
        var files = Array.from(input.files || []).slice(0, 10);

        if (!files.length) {
            preview.style.display = 'none';
            actions.style.display = 'none';
            return;
        }

        preview.style.display = 'flex';
        actions.style.display = 'flex';
        count.textContent = files.length + ' صورة محددة';

        files.forEach(function (file, index) {
            var col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-xl-3';

            var reader = new FileReader();
            reader.onload = function (e) {
                col.innerHTML =
                    '<img src="' + e.target.result + '" class="w-100 rounded" style="height:120px;object-fit:cover;">' +
                    '<input type="text" name="captions[' + index + ']" class="form-control form-control-sm mt-1" placeholder="وصف اختياري">';
                preview.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    }

    if (clear) {
        clear.addEventListener('click', function () {
            input.value = '';
            preview.innerHTML = '';
            preview.style.display = 'none';
            actions.style.display = 'none';
        });
    }
});
</script>
@endpush
