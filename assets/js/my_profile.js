/* global uploadAvatarUrl */
/* global apiUserPrograms */
/* global userID */
/* global profileUrl */
/* global saveUsername */
/* global saveEmailUrl */
/* global savePasswordUrl */
/* global deleteAccountUrl */
/* global toggleVisibilityUrl */
/* global uploadUrl */
/* global statusCodeOk */
/* global statusCodeUsernameAlreadyExists */
/* global statusCodeUsernameMissing */
/* global statusCodeUsernameInvalid */
/* global statusCodeUserEmailAlreadyExists */
/* global statusCodeUserEmailMissing */
/* global statusCodeUserEmailInvalid */
/* global statusCodeUsernamePasswordEqual */
/* global statusCodeUserPasswordTooShort */
/* global statusCodeUserPasswordTooLong */
/* global statusCodeUserPasswordNotEqualPassword2 */
/* global statusCodePasswordInvalid */
/* global successText */
/* global checkMailText */
/* global passwordUpdatedText */
/* global statusCodeUsernameContainsEmail */
/* global programCanNotChangeVisibilityTitle */
/* global programCanNotChangeVisibilityText */
/* global deleteConfirmationMessage */
/* global myProfileConfiguration */

import $ from 'jquery'
import './components/fullscreen_list_modal'
import './components/text_field'
import './components/tab_bar'
import { MyProfile } from './custom/MyProfile'
import { ProjectLoader } from './custom/ProjectLoader'
import { setImageUploadListener } from './custom/ImageUpload'
import { PasswordVisibilityToggle } from './components/password_visibility_toggle'
import { OwnProjectList } from './custom/own_project_list'
import Swal from 'sweetalert2'
import { deleteCookie } from './security/CookieHelper'

require('../styles/custom/profile.scss')

// eslint-disable-next-line no-new
new PasswordVisibilityToggle()

$(() => {
  setImageUploadListener(uploadAvatarUrl, '#avatar-upload', '#avatar-img')

  MyProfile(
    profileUrl,
    saveUsername,
    saveEmailUrl,
    savePasswordUrl,
    deleteAccountUrl,
    toggleVisibilityUrl,
    uploadUrl,
    parseInt(statusCodeOk),
    parseInt(statusCodeUsernameAlreadyExists),
    parseInt(statusCodeUsernameMissing),
    parseInt(statusCodeUsernameInvalid),
    parseInt(statusCodeUserEmailAlreadyExists),
    parseInt(statusCodeUserEmailMissing),
    parseInt(statusCodeUserEmailInvalid),
    parseInt(statusCodeUsernamePasswordEqual),
    parseInt(statusCodeUserPasswordTooShort),
    parseInt(statusCodeUserPasswordTooLong),
    parseInt(statusCodeUserPasswordNotEqualPassword2),
    parseInt(statusCodePasswordInvalid),
    successText,
    checkMailText,
    passwordUpdatedText,
    programCanNotChangeVisibilityTitle,
    programCanNotChangeVisibilityText,
    parseInt(statusCodeUsernameContainsEmail),
    deleteConfirmationMessage
  )

  const projectsContainer = document.getElementById('own-projects')
  const theme = projectsContainer.dataset.theme
  const baseUrl = projectsContainer.dataset.baseUrl
  const userId = projectsContainer.dataset.userId
  const emptyMessage = projectsContainer.dataset.emptyMessage

  const url = baseUrl + '/api/projects/user/' + userId
  // const url = baseUrl + '/api/projects/user'
  // TODO: switch to API endpoint without ID, but how to handle JWT authentication?

  new OwnProjectList(projectsContainer, url, theme, emptyMessage, myProfileConfiguration.projectActions).initialize()

  initProfilePictureChange()
  initSaveProfileSettings()
  initSaveSecuritySettings()
  initDeleteAccount()

  const programs = new ProjectLoader('#myprofile-programs', apiUserPrograms)
  programs.initProfile(userID)
})

const initProfilePictureChange = function () {
  Array.prototype.forEach.call(document.getElementsByClassName('profile__basic-info__avatar'), function (el) {
    el.addEventListener('click', function () {
      const input = document.createElement('input')
      input.type = 'file'
      input.onchange = event => {
        console.debug('new file', event, input.files)

        const reader = new window.FileReader()

        reader.onerror = () => {
          // TODO: hide spinner
          // TODO: show upload error message
        }
        reader.onload = event => {
          const image = event.currentTarget.result // base64 data url
          console.debug('Loading image', image
          )
          // TODO: API call and error handling
        }
        reader.readAsDataURL(input.files[0])
      }
      input.click()
    })
  })
}

const initSaveProfileSettings = function () {
  document.getElementById('profile_settings-save_action').addEventListener('click', () => {
    const form = document.getElementById('profile-settings-form')
    if (form.reportValidity() === true) {
      const formData = new window.FormData(form)
      console.debug('Save profile settings', Array.from(formData))
      // TODO: API call and error handling
    }
  })
}

const initSaveSecuritySettings = function () {
  document.getElementById('security_settings-save_action').addEventListener('click', () => {
    const form = document.getElementById('security-settings-form')
    if (form.reportValidity() === true) {
      const formData = new window.FormData(form)
      if (formData.get('_password') !== formData.get('_repeat-password')) {
        // TODO: show beautiful error
        alert('Passwords do not match!')
      } else {
        console.debug('Save security settings', Array.from(formData))
        // TODO: API call and error handling
      }
    }
  })
}

const initDeleteAccount = function () {
  const routingDataset = document.getElementById('js-api-routing').dataset
  document.getElementById('btn-delete-account').addEventListener('click', () => {
    const msgParts = myProfileConfiguration.userSettings.deleteAccount.confirmationText.split('\n')
    Swal.fire({
      title: msgParts[0],
      html: msgParts[1] + '<br><br>' + msgParts[2],
      icon: 'warning',
      showCancelButton: true,
      allowOutsideClick: false,
      customClass: {
        confirmButton: 'btn btn-danger',
        cancelButton: 'btn btn-outline-primary'
      },
      buttonsStyling: false,
      confirmButtonText: msgParts[3],
      cancelButtonText: msgParts[4]
    }).then((result) => {
      if (result.value) {
        $.post(myProfileConfiguration.userSettings.deleteAccount.url, null, function (data) {
          switch (parseInt(data.statusCode)) {
            case myProfileConfiguration.statusCodes.ok:
              deleteCookie('BEARER', routingDataset.baseUrl + '/')
              window.location.href = routingDataset.index
          }
        })
      }
    })
    $('.swal2-container.swal2-shown').css('background-color', 'rgba(255, 0, 0, 0.75)')// changes the color of the overlay
  })
}
