document.querySelectorAll(".upload-file__input").forEach(function (input) {
    input.addEventListener("change", function (event) {
        var file = event.target.files[0];
        var card = event.target.closest(".upload-file");
        var textbox = card.querySelector(".upload-file__textbox");
        var imgElement = card.querySelector(".upload-file__img__img");

        var prevSrc = textbox.querySelector("img").src;

        if (file) {
            var reader = new FileReader();
            reader.onload = function (e) {
                imgElement.src = e.target.result;

                $(card).find(".remove-img-icon").removeClass("d-none");
                textbox.style.display = "none";
                imgElement.style.display = "block";
            };
            reader.readAsDataURL(file);
        }

        // Remove image
        $(card)
            .find(".remove-img-icon")
            .on("click", function () {
                $(card).find(".upload-file__input").val("");
                $(card).find(".upload-file__img__img").attr("src", "");
                textbox.querySelector("img").src = prevSrc;
                textbox.style.display = "block";
                imgElement.style.display = "none";
                $(card).find(".remove-img-icon").addClass("d-none");
            });
    });
});
