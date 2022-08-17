<!DOCTYPE html>
<html>
<head>
    <title>SuperSlicer / PrusaSlicer  plate visualizer by Andrew T</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
</head>
<body class="bg-light">

<div class="container">
    <div class="row">
        <div class="col-12 col-md-6 offset-md-3 pt-4">
            <h3>SuperSlicer / PrusaSlicer plate visualizer</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-md-6 offset-md-3 pt-4">

            <form method="post" action="" enctype="multipart/form-data">
                <div class="form-group row">
                    <div class="col-12">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="file">
                            <label class="custom-file-label" for="customFile">Choose your .gcode file</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <input type="button" class="btn btn-primary btn-block" id="submit" value="Upload file">
                    </div>
                    <div class="col-6">
                        <input type="button" class="btn btn-success btn-block" id="print" value="Print the image">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-12 text-center pt-4">
            <h3>Plate render</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-12 pt-4 pb-4">
            <img id="render" class="img-fluid" src="render.php" style="display: none;">
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        bsCustomFileInput.init();

        document.getElementById('submit').onclick = function () {
            var fileElement = document.getElementById('file');
            var files = fileElement.files;
            var formData = new FormData();
            var file = files[0];
            formData.append('file', file, file.name);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'render.php', true);
            xhr.onload = function (e) {
                if (xhr.status === 200) {
                    document.getElementById('render').src = 'data:image/jpeg;base64, ' + xhr.response;
                    document.getElementById('render').style.display = 'block';
                } else {
                    alert('An error occurred!');
                }
            };
            // xhr.upload.onprogress = function (e) {
            //     // e.loaded/e.total
            // }
            xhr.send(formData);
        }

        document.getElementById('print').onclick = function () {
            if (document.getElementById('render').style.display == 'none') {
                return;
            }

            var win = window.open('', 'Print', 'width=1200,height=800,top=50,left=50,toolbars=no,scrollbars=yes,status=no,resizable=yes');

            win.document.write(
                '<html><head></head><body onload="window.print();window.close();"><img src="' +
                document.getElementById('render').src +
                '"></body></html>'
            );

            win.document.close();
            win.focus();
        }
    });
</script>

</body>
</html>

