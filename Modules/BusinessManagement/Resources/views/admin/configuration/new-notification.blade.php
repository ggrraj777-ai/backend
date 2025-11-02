@extends('adminmodule::layouts.master')

@section('title', translate('notification'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin-module/plugins/swiper@11/swiper-bundle.min.css') }}"/>
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.business.configuration.notification.push-store', $type) }}" method="POST"
              id="notification_setup_form">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-22 mb-4 text-capitalize">{{ translate('notifications') }}</h2>
                <div class="mb-4 overflow-x-auto">
                    <ul class="nav nav--tabs_two">
                        <li class="nav-item">
                            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'regular-trip'])}}"
                               class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/*') && !Request::is('admin/business/configuration/notification/firebase-configuration') ? "active":""}}">{{ translate('notification_message') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('admin.business.configuration.notification.firebase-configuration')}}"
                               class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/firebase-configuration') ? "active":""}}">{{ translate('firebase_configuration') }}</a>
                        </li>
                    </ul>
                </div>
                @include('businessmanagement::admin.configuration.partials._notification_inline_menu')
                <div class="firebase-push-notifications">
                    @include('businessmanagement::admin.configuration.partials._firebase-notification-fields')
                </div>
            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="d-flex justify-content-end py-4">
                        <button type="button" class="btn btn-primary text-capitalize submit-notifications">Submit
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{ asset('public/assets/admin-module/plugins/swiper@11/swiper-bundle.min.js') }}"></script>
    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#notification_setup_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('#server_key_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('.switcher_input').on('click', function () {
            if ($(this).attr('data-type') == 'push') {
                updateSettings(this)
            }
        })

        function updateSettings(obj) {
            $.ajax({
                url: '{{ route('admin.business.configuration.notification.notification-settings') }}',
                _method: 'PUT',
                data: {
                    id: $(obj).data('id'),
                    type: $(obj).data('type'),
                    status: ($(obj).prop("checked")) === true ? 1 : 0
                },
                beforeSend: function () {
                    $('.preloader').removeClass('d-none');
                },
                success: function (d) {
                    $('.preloader').addClass('d-none');
                    toastr.success("{{ translate('status_successfully_changed') }}");
                },
                error: function () {
                    $('.preloader').addClass('d-none');
                    toastr.error("{{ translate('status_change_failed') }}");

                }
            });
        }

        $(document).ready(function () {
            //----- sticky footer
            $(window).on('scroll', function () {
                const $footer = $('.footer-sticky');
                const scrollPosition = $(window).scrollTop() + $(window).height();
                const documentHeight = $(document).height();

                if (scrollPosition >= documentHeight - 5) {
                    $footer.addClass('no-shadow');
                } else {
                    $footer.removeClass('no-shadow');
                }
            });
        });

        $(".read-instruction").on('click', function () {
            const modalElement = document.getElementById('ReadInstructionSliderModal');
            let bootstrapModal = new bootstrap.Modal(modalElement);
            bootstrapModal.show();
        });

        $(".submit-notifications").on('click', function (e) {
            e.preventDefault();
            let form = $('#notification_setup_form');
            let formData = form.serialize();
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    toastr.success(response.success);
                    $('.firebase-push-notifications').empty().html(response.view);
                },
                error: function (xhr) {
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            $.each(xhr.responseJSON.errors, function (field, messages) {
                                messages.forEach(message => {
                                    toastr.error(message);
                                });
                            });
                        }
                        $.ajax({
                            url: '{{ route('admin.business.configuration.notification.firebase-push-notifications-fields', $type) }}',
                            type: 'GET',
                            success: function (response) {
                                $('.firebase-push-notifications').empty().html(response);
                            },
                            error: function () {
                                toastr.error('Something went wrong');
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            }
                        })
                    }
                }
            })
        });

        $(window).on("load", function () {
            if ($(".instruction-carousel_new").length) {
                let slideCount = $(".instruction-carousel_new .swiper-slide").length;
                let swiperPaginationCustom = $('.instruction-pagination-custom_new');

                swiperPaginationCustom.html(`<span class="active">1</span> / ${slideCount}`);

                const swiper = new Swiper(".instruction-carousel_new", {
                    direction: "horizontal",
                    autoHeight: true,
                    pagination: {
                        el: ".instruction-pagination",
                        clickable: true,
                    },
                    navigation: {
                        nextEl: [
                            document.querySelector(".instruction-carousel_new .swiper-button-next"),
                            document.querySelector(".bottom_arrow .swiper-button-next")
                        ],
                        prevEl: [
                            document.querySelector(".instruction-carousel_new .swiper-button-prev"),
                            document.querySelector(".bottom_arrow .swiper-button-prev")
                        ]
                    },
                    on: {
                        slideChange: () => {
                            swiperPaginationCustom.html(
                                `<span class="active">${swiper.realIndex + 1}</span> / ${swiper.slidesGrid.length}`
                            );
                        },
                    }
                });
            }
        });



    </script>
@endpush
